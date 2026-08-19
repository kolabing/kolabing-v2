<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\KolabSuggestion;
use App\Models\Profile;
use App\Services\Suggestions\SignalReasonRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One suggestion card.
 *
 * Two things happen here that happen nowhere else.
 *
 * **The copy is rendered.** `kolab_suggestions` stores signal keys and raw
 * params, never sentences — the nightly generator runs under the app's default
 * locale, so anything it rendered would reach every reader in that one language.
 * Every label, reason and title on the card is produced here, in the *caller's*
 * locale, by SignalReasonRenderer. The stored `reason_key` / `reason_params` /
 * `title_key` / `title_params` are internal and never reach a client.
 *
 * **The identity blur is applied.** See shouldBlurIdentity() below — it is the
 * single most misread rule in this codebase, so read that docblock before
 * touching it.
 */
class SuggestionResource extends JsonResource
{
    /**
     * @var KolabSuggestion
     */
    public $resource;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $blurred = $this->shouldBlurIdentity($request);

        return [
            'id' => $this->resource->id,
            'audience' => $this->resource->audience->value,
            'score' => $this->resource->score,
            'confidence' => $this->resource->confidence->value,
            'is_identity_blurred' => $blurred,
            'counterpart' => $this->counterpart($blurred),
            'signals' => $this->signals(),
            'suggested_format' => $this->suggestedFormat(),
            'shown_at' => $this->resource->shown_at?->toIso8601String(),
            'clicked_at' => $this->resource->clicked_at?->toIso8601String(),
            'expires_at' => $this->resource->expires_at?->toIso8601String(),
        ];
    }

    /**
     * A non-subscribed business sees the counterpart's identity masked, exactly
     * as Explore masks it (ROLES-AND-PERMISSIONS.md §2.4/§2.5). This is a
     * downstream effect of the two existing gates — creating a collaboration and
     * applying to a Kolab — and **not a new paywall** (§2.7): every non-identity
     * field on the card stays visible, so the business still sees the score, the
     * confidence, every reason and the whole proposed format.
     *
     * A community is never masked, in either direction, and there is no
     * community paywall of any kind. `isBusiness()` short-circuits first for
     * exactly that reason: `hasActiveSubscription()` already returns false for
     * every non-business, so testing the subscription alone would mask every
     * community viewer — the single most damaging regression available here.
     */
    private function shouldBlurIdentity(Request $request): bool
    {
        /** @var Profile|null $viewer */
        $viewer = $request->user();

        if ($viewer === null || ! $viewer->isBusiness()) {
            return false;
        }

        return ! $viewer->hasActiveSubscription();
    }

    /**
     * The counterpart may be gone: a profile deactivated after the batch ran is
     * filtered out of the list, but a row already in a client's hands can still
     * be opened, and that must render rather than 500.
     *
     * @return array{id: string|null, user_type: string|null, name: string|null, avatar_url: string|null}
     */
    private function counterpart(bool $blurred): array
    {
        $profile = $this->resource->counterpartProfile;

        return [
            'id' => $profile?->id,
            'user_type' => $profile?->user_type?->value,
            'name' => $blurred ? null : $this->displayName($profile),
            'avatar_url' => $blurred ? null : $profile?->avatar_url,
        ];
    }

    private function displayName(?Profile $profile): ?string
    {
        if ($profile === null) {
            return null;
        }

        return $profile->isBusiness()
            ? $profile->businessProfile?->name
            : $profile->communityProfile?->name;
    }

    /**
     * Signals with their copy rendered, in the order the scorer wrote them.
     *
     * A signal whose label or reason renders blank is **dropped**, not shipped:
     * SignalReasonRenderer returns an empty string for a row written by an older
     * deploy whose key this code no longer has, and a blank line on a card is
     * worse than one fewer line. `weight` stays internal — it is scoring config,
     * not a claim about the partner.
     *
     * @return array<int, array{key: string, label: string, reason: string, score: float}>
     */
    private function signals(): array
    {
        $renderer = app(SignalReasonRenderer::class);
        $rendered = [];

        foreach ($this->normalizeList($this->resource->signals) as $signal) {
            $copy = $renderer->render($signal);

            if ($copy['label'] === '' || $copy['reason'] === '') {
                continue;
            }

            $rendered[] = [
                'key' => is_string($signal['key'] ?? null) ? $signal['key'] : '',
                'label' => $copy['label'],
                'reason' => $copy['reason'],
                'score' => round((float) ($signal['score'] ?? 0), 2),
            ];
        }

        return $rendered;
    }

    /**
     * The proposed event, with `title_key` / `title_params` replaced by a
     * rendered title and the notes replaced by rendered sentences. A title that
     * renders blank becomes null for the same reason a blank signal is dropped.
     *
     * @return array<string, mixed>
     */
    private function suggestedFormat(): array
    {
        $renderer = app(SignalReasonRenderer::class);
        $format = is_array($this->resource->suggested_format) ? $this->resource->suggested_format : [];

        $title = $renderer->renderTitle($format);

        return [
            'title' => $title === '' ? null : $title,
            'intent_type' => is_string($format['intent_type'] ?? null) ? $format['intent_type'] : null,
            'weekday' => isset($format['weekday']) && is_numeric($format['weekday']) ? (int) $format['weekday'] : null,
            'time_of_day' => is_string($format['time_of_day'] ?? null) ? $format['time_of_day'] : null,
            'expected_attendance' => isset($format['expected_attendance']) && is_numeric($format['expected_attendance'])
                ? (int) $format['expected_attendance']
                : null,
            'offer' => array_values($this->normalizeStrings($format['offer'] ?? [])),
            'expects' => array_values($this->normalizeStrings($format['expects'] ?? [])),
            'notes' => $this->notes($renderer, $format['notes'] ?? []),
            'attendance_basis' => is_string($format['attendance_basis'] ?? null) ? $format['attendance_basis'] : null,
            'weekday_basis' => is_string($format['weekday_basis'] ?? null) ? $format['weekday_basis'] : null,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function notes(SignalReasonRenderer $renderer, mixed $notes): array
    {
        $rendered = [];

        foreach ($this->normalizeList($notes) as $note) {
            $sentence = $renderer->render($note)['reason'];

            if ($sentence !== '') {
                $rendered[] = $sentence;
            }
        }

        return $rendered;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    /**
     * @return array<int, string>
     */
    private function normalizeStrings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_map(static fn (mixed $item): string => (string) $item, array_filter($value, 'is_scalar'));
    }
}

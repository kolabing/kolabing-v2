<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateSalesPitchRequest;
use App\Models\Profile;
use App\Models\SalesOutreachDraft;
use App\Models\Scopes\ActiveProfileScope;
use App\Services\OpenAi\OpenAiClient;
use App\Services\SalesOutreach\RevenueEstimator;
use App\Services\SalesOutreach\SalesOutreachService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * The sales-mailing surface (BE-NF-65).
 *
 * Pick a business, pick a community to pitch them, generate Kolab ideas and the
 * email, draw a cover, read it, then send. Every step before the last one is
 * reversible; the last one is not, which is why it is its own action behind its own
 * preview rather than a checkbox on the generate form.
 *
 * Generation calls a paid API and takes seconds, so failures are shown as flash
 * errors on the form the maintainer is already looking at — never as a 500.
 */
class SalesMailingController extends Controller
{
    public function __construct(
        private readonly SalesOutreachService $outreach,
        private readonly RevenueEstimator $revenue,
        private readonly OpenAiClient $client,
    ) {}

    public function index(): View
    {
        return view('admin.sales-mailing.index', [
            'drafts' => SalesOutreachDraft::query()
                ->with(['business.businessProfile', 'community.communityProfile'])
                ->latest()
                ->paginate(20),
            'businesses' => $this->profilesOfType(UserType::Business),
            'communities' => $this->profilesOfType(UserType::Community),
            'locales' => (array) config('sales_outreach.locales'),
            'defaultAvgSpendCents' => $this->revenue->defaultAvgSpendCents(),
            'currency' => (string) config('sales_outreach.revenue.currency'),
            'isConfigured' => $this->client->isConfigured(),
        ]);
    }

    public function generate(GenerateSalesPitchRequest $request): RedirectResponse
    {
        try {
            // Queued: the research plus two model calls take long enough in
            // production to exceed Cloudflare's edge timeout (BE-FX-63).
            $draft = $this->outreach->queuePitch($request->validated(), $request->user());
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('Generation failed. Nothing was saved or sent.'));
        }

        return redirect()->route('admin.sales-mailing.edit', $draft)
            ->with('status', __('Writing the pitch — it appears here in under a minute.'));
    }

    public function edit(SalesOutreachDraft $draft): View
    {
        return view('admin.sales-mailing.edit', [
            'draft' => $draft->load(['business.businessProfile', 'community.communityProfile']),
            'currency' => (string) config('sales_outreach.revenue.currency'),
        ]);
    }

    public function selectIdea(Request $request, SalesOutreachDraft $draft): RedirectResponse
    {
        $index = (int) $request->input('index');

        return $this->attempt(
            fn () => $this->outreach->selectIdea($draft, $index),
            $draft,
            __('Idea switched and the email rewritten around it.'),
        );
    }

    /**
     * Queue the cover — this returns in milliseconds (BE-FX-61).
     *
     * It used to draw the image inline and produced a Cloudflare 504 in production:
     * generation measured ~25s and the upload followed it. The work now happens on a
     * worker and this page reports its state.
     */
    public function generateImage(SalesOutreachDraft $draft): RedirectResponse
    {
        return $this->attempt(
            fn () => $this->outreach->queueCoverImage($draft),
            $draft,
            __('Cover image queued — it appears here in under a minute.'),
        );
    }

    public function updateCopy(Request $request, SalesOutreachDraft $draft): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body_markdown' => ['required', 'string', 'max:8000'],
        ]);

        return $this->attempt(
            fn () => $this->outreach->updateCopy($draft, $data),
            $draft,
            __('Copy saved.'),
        );
    }

    /** The mandatory read-before-send step, same shape as the welcome email's. */
    public function preview(SalesOutreachDraft $draft): View
    {
        return view('admin.sales-mailing.preview', [
            'draft' => $draft->load(['business.businessProfile', 'community.communityProfile']),
            'html' => $this->outreach->previewHtml($draft),
        ]);
    }

    public function send(SalesOutreachDraft $draft): RedirectResponse
    {
        if (! $draft->isWritten()) {
            return back()->with('error', __('This pitch is not written yet.'));
        }

        try {
            $this->outreach->send($draft);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('Sending failed. The pitch was not sent.'));
        }

        return redirect()->route('admin.sales-mailing.index')
            ->with('status', __('Pitch sent.'));
    }

    /**
     * Run a step, and put any failure back on the screen the maintainer is on.
     *
     * @param  callable(): mixed  $step
     */
    private function attempt(callable $step, SalesOutreachDraft $draft, string $success): RedirectResponse
    {
        try {
            $step();
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('That step failed. Nothing was changed.'));
        }

        return redirect()->route('admin.sales-mailing.edit', $draft)->with('status', $success);
    }

    /**
     * Candidates for the two pickers.
     *
     * Deactivated accounts are excluded via the global scope rather than shown and
     * disabled: pitching a switched-off business is never the intent, and a listed
     * community that is switched off cannot honour the collaboration anyway.
     *
     * @return \Illuminate\Support\Collection<int, Profile>
     */
    private function profilesOfType(UserType $type): \Illuminate\Support\Collection
    {
        return Profile::query()
            ->where('user_type', $type)
            ->whereNotNull('email')
            ->with([
                'businessProfile' => fn ($q) => $q->withoutGlobalScope(ActiveProfileScope::class),
                'communityProfile' => fn ($q) => $q->withoutGlobalScope(ActiveProfileScope::class),
            ])
            ->get()
            ->sortBy(fn (Profile $p): string => mb_strtolower($this->displayName($p)))
            ->values();
    }

    private function displayName(Profile $profile): string
    {
        return $profile->businessProfile?->name
            ?? $profile->communityProfile?->name
            ?? $profile->email;
    }
}

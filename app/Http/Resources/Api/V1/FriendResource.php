<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * A friend / friend-request list item.
 *
 * Shape: { profile_id, name, avatar_url, user_type }. Name/avatar are resolved
 * exactly like CommunityMemberResource (attendee name lives on profiles.name
 * when present, otherwise the extended profile name, otherwise the email
 * local-part).
 *
 * @mixin Profile
 */
class FriendResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'profile_id' => $this->id,
            'name' => $this->resolveName(),
            'avatar_url' => $this->resolveAvatarUrl(),
            'user_type' => $this->user_type->value,
        ];
    }

    private function resolveName(): ?string
    {
        // Attendee name lives on profiles.name when the column is present.
        $direct = $this->resource->getAttribute('name');
        if (is_string($direct) && trim($direct) !== '') {
            return $direct;
        }

        $extended = $this->getExtendedProfile();
        if ($extended && ! empty($extended->name)) {
            return $extended->name;
        }

        return Str::before($this->email, '@');
    }

    private function resolveAvatarUrl(): ?string
    {
        $extended = $this->getExtendedProfile();

        $value = $this->avatar_url ?? ($extended->profile_photo ?? null);

        return $this->absoluteUrl($value);
    }

    /**
     * Serialize a stored avatar value as an absolute URL (matches
     * PublicProfileResource behaviour).
     */
    private function absoluteUrl(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($value, '/');
    }
}

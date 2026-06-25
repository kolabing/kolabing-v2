<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $profile_id
 * @property string|null $name
 * @property string|null $about
 * @property string|null $community_type
 * @property int|null $community_size
 * @property string|null $city_id
 * @property string|null $instagram
 * @property string|null $tiktok
 * @property string|null $website
 * @property string|null $profile_photo
 * @property bool $is_featured
 * @property array<int, array{type: string, url: string, is_public: bool}>|null $verification_channels
 * @property string $verification_status
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property string|null $verified_by
 * @property string|null $rejection_reason
 * @property \Illuminate\Support\Carbon|null $verification_flagged_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $profile
 * @property-read City|null $city
 */
class CommunityProfile extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'profile_id',
        'name',
        'about',
        'community_type',
        'community_size',
        'city_id',
        'instagram',
        'tiktok',
        'website',
        'profile_photo',
        'is_featured',
        'verification_channels',
        'verification_status',
        'verified_at',
        'verified_by',
        'rejection_reason',
        'verification_flagged_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'community_size' => 'integer',
            'verification_channels' => 'array',
            'verified_at' => 'datetime',
            'verification_flagged_at' => 'datetime',
        ];
    }

    /**
     * Mirror profile_photo onto the base profile's avatar_url whenever it
     * changes, regardless of which call site wrote it. The discovery feed
     * and other surfaces fall back to Profile::avatar_url when a kolab has no
     * media of its own, so that fallback must always reflect the user's
     * current photo (it used to only sync to the separate `communities`
     * table, leaving Profile::avatar_url stale or null).
     */
    protected static function booted(): void
    {
        static::saved(function (self $communityProfile): void {
            if ($communityProfile->wasChanged('profile_photo')) {
                Profile::whereKey($communityProfile->profile_id)
                    ->update(['avatar_url' => $communityProfile->profile_photo]);
            }
        });
    }

    /**
     * Whether this community is verified (status == verified).
     */
    public function isVerified(): bool
    {
        return $this->verification_status === VerificationStatus::Verified->value;
    }

    /**
     * The subset of verification channels the community marked public, shaped for
     * non-owner viewers (businesses): each as { type, url } with NO is_public and
     * NO private items. Default visibility is private (is_public absent => false).
     *
     * @return array<int, array{type: string, url: string}>
     */
    public function publicChannels(): array
    {
        $channels = $this->verification_channels ?? [];

        $public = [];

        foreach ($channels as $channel) {
            if (! is_array($channel) || ! isset($channel['type'], $channel['url'])) {
                continue;
            }

            if (filter_var($channel['is_public'] ?? false, FILTER_VALIDATE_BOOLEAN) !== true) {
                continue;
            }

            $public[] = [
                'type' => (string) $channel['type'],
                'url' => (string) $channel['url'],
            ];
        }

        return $public;
    }

    /**
     * Apply the shared verification transition when a community submits or edits
     * its proof channels. Mutates (but does NOT persist) the model:
     *
     *   - unverified | rejected → pending (with the new channels)
     *   - verified → stays verified, but verification_flagged_at = now() so the
     *     admin is flagged the channels changed (edit-after-verified rule).
     *   - pending → stays pending (channels just updated).
     *
     * @param  array<int, array{type: string, url: string, is_public: bool}>  $channels
     */
    public function applyVerificationChannelSubmission(array $channels): void
    {
        $this->verification_channels = $channels;

        $current = $this->verification_status ?? VerificationStatus::Unverified->value;

        if ($current === VerificationStatus::Verified->value) {
            // Daniel's rule: never demote a verified community on edit; flag it.
            $this->verification_flagged_at = Carbon::now();

            return;
        }

        if (
            $current === VerificationStatus::Unverified->value
            || $current === VerificationStatus::Rejected->value
        ) {
            $this->verification_status = VerificationStatus::Pending->value;
            $this->rejection_reason = null;
        }
    }

    /**
     * Get the profile that owns this community profile.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Get the city where this community member is located.
     *
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}

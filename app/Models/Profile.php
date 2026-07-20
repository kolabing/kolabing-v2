<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string $id
 * @property string $email
 * @property string|null $phone_number
 * @property string|null $name
 * @property string|null $handle
 * @property array<int, string>|null $interests
 * @property string|null $city_id
 * @property UserType $user_type
 * @property string|null $google_id
 * @property string|null $apple_id
 * @property string|null $password
 * @property string|null $avatar_url
 * @property string|null $device_token
 * @property string|null $device_platform
 * @property string|null $preferred_locale
 * @property \Illuminate\Support\Carbon|null $terms_accepted_at
 * @property string|null $terms_version
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read AttendeeProfile|null $attendeeProfile
 * @property-read City|null $city
 * @property-read BusinessProfile|null $businessProfile
 * @property-read CommunityProfile|null $communityProfile
 * @property-read BusinessSubscription|null $subscription
 * @property-read NotificationPreference|null $notificationPreferences
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Application> $applications
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Collaboration> $createdCollaborations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Collaboration> $appliedCollaborations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProfileGalleryPhoto> $galleryPhotos
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Event> $events
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Notification> $notifications
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventCheckin> $eventCheckins
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RewardClaim> $rewardClaims
 * @property-read \Illuminate\Database\Eloquent\Collection<int, BadgeAward> $badgeAwards
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Badge> $badges
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Kolab> $kolabs
 * @property-read Wallet|null $wallet
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PointLedger> $pointLedger
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EarnedBadge> $earnedBadges
 * @property-read ReferralCode|null $referralCode
 * @property-read ReferralRedemption|null $referralRedemption
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WithdrawalRequest> $withdrawalRequests
 */
class Profile extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasUuids;
    use Notifiable;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'profiles';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'phone_number',
        'user_type',
        'name',
        'handle',
        'interests',
        'city_id',
        'google_id',
        'apple_id',
        'password',
        'avatar_url',
        'email_verified_at',
        'device_token',
        'device_platform',
        'preferred_locale',
        'last_active_at',
        'analytics_opt_out',
        'terms_accepted_at',
        'terms_version',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'google_id',
        'apple_id',
        'password',
        'device_token',
        'device_platform',
        'is_test_user',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_type' => UserType::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_test_user' => 'boolean',
            'last_active_at' => 'datetime',
            'analytics_opt_out' => 'boolean',
            'interests' => 'array',
            'terms_accepted_at' => 'datetime',
        ];
    }

    /**
     * Get the business profile for this user.
     *
     * @return HasOne<BusinessProfile, $this>
     */
    public function businessProfile(): HasOne
    {
        return $this->hasOne(BusinessProfile::class);
    }

    /**
     * Get the community profile for this user.
     *
     * @return HasOne<CommunityProfile, $this>
     */
    public function communityProfile(): HasOne
    {
        return $this->hasOne(CommunityProfile::class);
    }

    /**
     * Get the attendee profile for this user.
     *
     * @return HasOne<AttendeeProfile, $this>
     */
    public function attendeeProfile(): HasOne
    {
        return $this->hasOne(AttendeeProfile::class);
    }

    /**
     * Get the city for this base profile (used by attendees, whose identity —
     * name, avatar, city — lives on the base `profiles` record rather than an
     * extended profile).
     *
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Get the subscription for this user (business only).
     *
     * @return HasOne<BusinessSubscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(BusinessSubscription::class);
    }

    /**
     * Get the notification preferences for this user.
     *
     * @return HasOne<NotificationPreference, $this>
     */
    public function notificationPreferences(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    /**
     * Get applications submitted by this profile.
     *
     * @return HasMany<Application, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'applicant_profile_id');
    }

    /**
     * Get collaborations where this profile is the creator.
     *
     * @return HasMany<Collaboration, $this>
     */
    public function createdCollaborations(): HasMany
    {
        return $this->hasMany(Collaboration::class, 'creator_profile_id');
    }

    /**
     * Get collaborations where this profile is the applicant.
     *
     * @return HasMany<Collaboration, $this>
     */
    public function appliedCollaborations(): HasMany
    {
        return $this->hasMany(Collaboration::class, 'applicant_profile_id');
    }

    /**
     * Get gallery photos for this profile.
     *
     * @return HasMany<ProfileGalleryPhoto, $this>
     */
    public function galleryPhotos(): HasMany
    {
        return $this->hasMany(ProfileGalleryPhoto::class);
    }

    /**
     * Get events for this profile.
     *
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'profile_id');
    }

    /**
     * Communities this profile owns (as a Community Leader).
     *
     * @return HasMany<Community, $this>
     */
    public function ownedCommunities(): HasMany
    {
        return $this->hasMany(Community::class, 'owner_profile_id');
    }

    /**
     * Community memberships this profile holds (as a Community Member).
     *
     * @return HasMany<CommunityMember, $this>
     */
    public function communityMemberships(): HasMany
    {
        return $this->hasMany(CommunityMember::class, 'profile_id');
    }

    /**
     * Get notifications for this profile.
     *
     * @return HasMany<Notification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get event checkins for this profile.
     *
     * @return HasMany<EventCheckin, $this>
     */
    public function eventCheckins(): HasMany
    {
        return $this->hasMany(EventCheckin::class);
    }

    /**
     * Get reward claims for this profile.
     *
     * @return HasMany<RewardClaim, $this>
     */
    public function rewardClaims(): HasMany
    {
        return $this->hasMany(RewardClaim::class);
    }

    /**
     * Get badge awards for this profile.
     *
     * @return HasMany<BadgeAward, $this>
     */
    public function badgeAwards(): HasMany
    {
        return $this->hasMany(BadgeAward::class);
    }

    /**
     * Get badges earned by this profile.
     *
     * @return BelongsToMany<Badge, $this>
     */
    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'badge_awards')
            ->withPivot('awarded_at')
            ->withTimestamps();
    }

    /**
     * Get kolabs created by this profile.
     *
     * @return HasMany<Kolab, $this>
     */
    public function kolabs(): HasMany
    {
        return $this->hasMany(Kolab::class, 'creator_profile_id');
    }

    /**
     * Kolabs this profile has saved/bookmarked.
     *
     * @return BelongsToMany<Kolab, $this>
     */
    public function savedKolabs(): BelongsToMany
    {
        return $this->belongsToMany(Kolab::class, 'saved_kolabs', 'profile_id', 'kolab_id')
            ->withTimestamps();
    }

    /**
     * Get the business partner status for this profile.
     *
     * @return HasOne<BusinessPartnerStatus, $this>
     */
    public function businessPartnerStatus(): HasOne
    {
        return $this->hasOne(BusinessPartnerStatus::class);
    }

    /**
     * Get the gamification wallet for this profile.
     *
     * @return HasOne<Wallet, $this>
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * Get point ledger entries for this profile.
     *
     * @return HasMany<PointLedger, $this>
     */
    public function pointLedger(): HasMany
    {
        return $this->hasMany(PointLedger::class);
    }

    /**
     * Get earned gamification badges for this profile.
     *
     * @return HasMany<EarnedBadge, $this>
     */
    public function earnedBadges(): HasMany
    {
        return $this->hasMany(EarnedBadge::class);
    }

    /**
     * Get the referral code for this profile.
     *
     * @return HasOne<ReferralCode, $this>
     */
    public function referralCode(): HasOne
    {
        return $this->hasOne(ReferralCode::class);
    }

    /**
     * Get the referral redemption used by this profile.
     *
     * @return HasOne<ReferralRedemption, $this>
     */
    public function referralRedemption(): HasOne
    {
        return $this->hasOne(ReferralRedemption::class, 'referred_profile_id');
    }

    /**
     * Get withdrawal requests for this profile.
     *
     * @return HasMany<WithdrawalRequest, $this>
     */
    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class);
    }

    /**
     * Check if the user is a business user.
     */
    public function isBusiness(): bool
    {
        return $this->user_type === UserType::Business;
    }

    /**
     * Check if the user is a community user.
     */
    public function isCommunity(): bool
    {
        return $this->user_type === UserType::Community;
    }

    /**
     * Check if the user is an attendee user.
     */
    public function isAttendee(): bool
    {
        return $this->user_type === UserType::Attendee;
    }

    /**
     * Check if the business user has an active subscription.
     * Community users always return false.
     */
    public function hasActiveSubscription(): bool
    {
        if (! $this->isBusiness()) {
            return false;
        }

        if ($this->is_test_user) {
            return true;
        }

        return $this->subscription?->isActive() ?? false;
    }

    /**
     * Determine whether the user still needs to accept the current published
     * version of the Terms of Service + Privacy Policy. True when they have
     * never accepted, or accepted an older version than the one in effect.
     */
    public function needsTermsAcceptance(): bool
    {
        return $this->terms_version !== app(\App\Services\Admin\CompanySettingService::class)->termsVersion();
    }

    /**
     * Get the extended profile based on user type.
     */
    public function getExtendedProfile(): AttendeeProfile|BusinessProfile|CommunityProfile|null
    {
        if ($this->isBusiness()) {
            return $this->businessProfile;
        }

        if ($this->isAttendee()) {
            return $this->attendeeProfile;
        }

        return $this->communityProfile;
    }

    /**
     * Determine whether the profile has completed its onboarding flow.
     */
    public function onboardingCompleted(): bool
    {
        if ($this->isAttendee()) {
            return filled($this->name)
                && filled($this->handle)
                && filled($this->city_id)
                && ! empty($this->interests);
        }

        if ($this->isBusiness()) {
            return filled($this->businessProfile?->name)
                && filled($this->businessProfile?->business_type)
                && filled($this->businessProfile?->city_id);
        }

        if ($this->isCommunity()) {
            return filled($this->communityProfile?->name)
                && filled($this->communityProfile?->community_type)
                && filled($this->communityProfile?->city_id);
        }

        return false;
    }
}

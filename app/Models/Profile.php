<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
 * @property UserType $user_type
 * @property string|null $google_id
 * @property string|null $password
 * @property string|null $avatar_url
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read AttendeeProfile|null $attendeeProfile
 * @property-read BusinessProfile|null $businessProfile
 * @property-read CommunityProfile|null $communityProfile
 * @property-read BusinessSubscription|null $subscription
 * @property-read NotificationPreference|null $notificationPreferences
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CollabOpportunity> $createdOpportunities
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Application> $applications
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Collaboration> $createdCollaborations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Collaboration> $appliedCollaborations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProfileGalleryPhoto> $galleryPhotos
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Event> $events
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Notification> $notifications
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventCheckin> $eventCheckins
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RewardClaim> $rewardClaims
 * @property-read bool $onboarding_completed
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
        'google_id',
        'password',
        'avatar_url',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'google_id',
        'password',
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
     * Get opportunities created by this profile.
     *
     * @return HasMany<CollabOpportunity, $this>
     */
    public function createdOpportunities(): HasMany
    {
        return $this->hasMany(CollabOpportunity::class, 'creator_profile_id');
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

        return $this->subscription?->isActive() ?? false;
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
     * Check if onboarding is completed.
     * Onboarding is complete when: name + city + at least one social field.
     */
    public function getOnboardingCompletedAttribute(): bool
    {
        $extendedProfile = $this->getExtendedProfile();

        if (! $extendedProfile) {
            return false;
        }

        $hasName = ! empty($extendedProfile->name);
        $hasCity = ! empty($extendedProfile->city_id);
        $hasSocialField = ! empty($extendedProfile->instagram)
            || ! empty($extendedProfile->website)
            || ! empty($this->phone_number)
            || ($extendedProfile instanceof CommunityProfile && ! empty($extendedProfile->tiktok));

        return $hasName && $hasCity && $hasSocialField;
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CollaborationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $application_id
 * @property string|null $kolab_id
 * @property string $creator_profile_id
 * @property string $applicant_profile_id
 * @property string $business_profile_id
 * @property string $community_profile_id
 * @property CollaborationStatus $status
 * @property \Illuminate\Support\Carbon|null $scheduled_date
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property array<string, mixed>|null $contact_methods
 * @property string|null $event_id
 * @property string|null $qr_code_url
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Application $application
 * @property-read Kolab|null $kolab
 * @property-read Profile $creatorProfile
 * @property-read Profile $applicantProfile
 * @property-read BusinessProfile $businessProfile
 * @property-read CommunityProfile $communityProfile
 * @property-read Event|null $event
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Challenge> $challenges
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CollaborationReview> $reviews
 */
class Collaboration extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'application_id',
        'kolab_id',
        'creator_profile_id',
        'applicant_profile_id',
        'business_profile_id',
        'community_profile_id',
        'status',
        'scheduled_date',
        'activated_at',
        'completed_at',
        'completion_reason',
        'completed_by_profile_id',
        'auto_completed_at',
        'cancelled_at',
        'cancellation_reason',
        'cancelled_by_profile_id',
        'contact_methods',
        'event_id',
        'qr_code_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CollaborationStatus::class,
            'scheduled_date' => 'date',
            'activated_at' => 'datetime',
            'completed_at' => 'datetime',
            'auto_completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'contact_methods' => 'array',
        ];
    }

    /**
     * Get the application that created this collaboration.
     *
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Get the canonical Kolab.
     *
     * @return BelongsTo<Kolab, $this>
     */
    public function kolab(): BelongsTo
    {
        return $this->belongsTo(Kolab::class, 'kolab_id');
    }

    /**
     * Get the profile that created the opportunity.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function creatorProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'creator_profile_id');
    }

    /**
     * Get the profile that applied to the opportunity.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function applicantProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'applicant_profile_id');
    }

    /**
     * Lightweight completion confirmations (yes/no/not_yet), one per
     * participant. Gates /complete (PR 1, 2026-06-26) — distinct from the
     * rich, optional `feedbacks()` relation.
     *
     * @return HasMany<CollaborationCompletion, $this>
     */
    public function completions(): HasMany
    {
        return $this->hasMany(CollaborationCompletion::class);
    }

    /**
     * Get the business profile involved in this collaboration.
     *
     * @return BelongsTo<BusinessProfile, $this>
     */
    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(BusinessProfile::class);
    }

    /**
     * Get the community profile involved in this collaboration.
     *
     * @return BelongsTo<CommunityProfile, $this>
     */
    public function communityProfile(): BelongsTo
    {
        return $this->belongsTo(CommunityProfile::class);
    }

    /**
     * Get the event associated with this collaboration.
     *
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the challenges selected for this collaboration.
     *
     * @return BelongsToMany<Challenge, $this>
     */
    public function challenges(): BelongsToMany
    {
        return $this->belongsToMany(Challenge::class, 'collaboration_challenges')
            ->withTimestamps();
    }

    /**
     * Get the reviews submitted by participants for this collaboration.
     *
     * @return HasMany<CollaborationReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(CollaborationReview::class);
    }

    /**
     * Get the rich feedback rows submitted by participants for this collaboration.
     *
     * @return HasMany<CollaborationFeedback, $this>
     */
    public function feedbacks(): HasMany
    {
        return $this->hasMany(CollaborationFeedback::class);
    }

    /**
     * Per-challenge bonuses the business added on top.
     *
     * @return HasMany<CollaborationChallengeBonus, $this>
     */
    public function challengeBonuses(): HasMany
    {
        return $this->hasMany(CollaborationChallengeBonus::class);
    }

    /**
     * Check if the collaboration is scheduled.
     */
    public function isScheduled(): bool
    {
        return $this->status === CollaborationStatus::Scheduled;
    }

    /**
     * Check if the collaboration is active.
     */
    public function isActive(): bool
    {
        return $this->status === CollaborationStatus::Active;
    }

    /**
     * Check if the collaboration is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === CollaborationStatus::Completed;
    }

    /**
     * Check if the collaboration is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === CollaborationStatus::Cancelled;
    }

    /**
     * Check if the collaboration can be activated.
     */
    public function canBeActivated(): bool
    {
        return $this->isScheduled();
    }

    /**
     * Check if the collaboration can be completed.
     */
    public function canBeCompleted(): bool
    {
        return $this->isScheduled() || $this->isActive();
    }

    /**
     * Check if the collaboration can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return $this->isScheduled() || $this->isActive();
    }

    /**
     * Check if the collaboration is in a terminal state.
     */
    public function isInTerminalState(): bool
    {
        return $this->isCompleted() || $this->isCancelled();
    }
}

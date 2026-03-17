<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OfferStatus;
use App\Enums\UserType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $creator_profile_id
 * @property UserType $creator_profile_type
 * @property string $title
 * @property string|null $description
 * @property OfferStatus $status
 * @property array<string, mixed>|null $business_offer
 * @property array<string, mixed>|null $community_deliverables
 * @property array<string>|null $categories
 * @property string|null $availability_mode
 * @property \Illuminate\Support\Carbon|null $availability_start
 * @property \Illuminate\Support\Carbon|null $availability_end
 * @property string|null $selected_time
 * @property array<int>|null $recurring_days
 * @property string|null $venue_mode
 * @property string|null $address
 * @property string|null $preferred_city
 * @property string|null $offer_photo
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $creatorProfile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Application> $applications
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Collaboration> $collaborations
 */
class CollabOpportunity extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'collab_opportunities';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'creator_profile_id',
        'creator_profile_type',
        'title',
        'description',
        'status',
        'business_offer',
        'community_deliverables',
        'categories',
        'availability_mode',
        'availability_start',
        'availability_end',
        'selected_time',
        'recurring_days',
        'venue_mode',
        'address',
        'preferred_city',
        'offer_photo',
        'published_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'creator_profile_type' => UserType::class,
            'status' => OfferStatus::class,
            'business_offer' => 'array',
            'community_deliverables' => 'array',
            'categories' => 'array',
            'availability_start' => 'date',
            'availability_end' => 'date',
            'recurring_days' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Get the profile that created this opportunity.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function creatorProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'creator_profile_id');
    }

    /**
     * Get all applications for this opportunity.
     *
     * @return HasMany<Application, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'collab_opportunity_id');
    }

    /**
     * Get all collaborations for this opportunity.
     *
     * @return HasMany<Collaboration, $this>
     */
    public function collaborations(): HasMany
    {
        return $this->hasMany(Collaboration::class, 'collab_opportunity_id');
    }

    /**
     * Check if the opportunity is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === OfferStatus::Draft;
    }

    /**
     * Check if the opportunity is published.
     */
    public function isPublished(): bool
    {
        return $this->status === OfferStatus::Published;
    }

    /**
     * Check if the opportunity is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === OfferStatus::Closed;
    }

    /**
     * Check if the opportunity is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === OfferStatus::Completed;
    }

    /**
     * Check if the opportunity is open for applications.
     */
    public function isOpenForApplications(): bool
    {
        return $this->isPublished();
    }

    /**
     * Check if the opportunity was created by a business user.
     */
    public function isCreatedByBusiness(): bool
    {
        return $this->creator_profile_type === UserType::Business;
    }

    /**
     * Check if the opportunity was created by a community user.
     */
    public function isCreatedByCommunity(): bool
    {
        return $this->creator_profile_type === UserType::Community;
    }

    /**
     * Get the count of pending applications.
     */
    public function getPendingApplicationsCount(): int
    {
        return $this->applications()
            ->where('status', 'pending')
            ->count();
    }

    /**
     * Get the count of accepted applications.
     */
    public function getAcceptedApplicationsCount(): int
    {
        return $this->applications()
            ->where('status', 'accepted')
            ->count();
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\UserType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $collab_opportunity_id
 * @property string $applicant_profile_id
 * @property UserType $applicant_profile_type
 * @property string|null $message
 * @property string|null $availability
 * @property ApplicationStatus $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read CollabOpportunity $collabOpportunity
 * @property-read Profile $applicantProfile
 * @property-read Collaboration|null $collaboration
 */
class Application extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'collab_opportunity_id',
        'applicant_profile_id',
        'applicant_profile_type',
        'message',
        'availability',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'applicant_profile_type' => UserType::class,
            'status' => ApplicationStatus::class,
        ];
    }

    /**
     * Get the collaboration opportunity this application is for.
     *
     * @return BelongsTo<CollabOpportunity, $this>
     */
    public function collabOpportunity(): BelongsTo
    {
        return $this->belongsTo(CollabOpportunity::class, 'collab_opportunity_id');
    }

    /**
     * Get the profile that submitted this application.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function applicantProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'applicant_profile_id');
    }

    /**
     * Get the collaboration created from this application (if accepted).
     *
     * @return HasOne<Collaboration, $this>
     */
    public function collaboration(): HasOne
    {
        return $this->hasOne(Collaboration::class);
    }

    /**
     * Check if the application is pending.
     */
    public function isPending(): bool
    {
        return $this->status === ApplicationStatus::Pending;
    }

    /**
     * Check if the application has been accepted.
     */
    public function isAccepted(): bool
    {
        return $this->status === ApplicationStatus::Accepted;
    }

    /**
     * Check if the application has been declined.
     */
    public function isDeclined(): bool
    {
        return $this->status === ApplicationStatus::Declined;
    }

    /**
     * Check if the application has been withdrawn.
     */
    public function isWithdrawn(): bool
    {
        return $this->status === ApplicationStatus::Withdrawn;
    }

    /**
     * Check if the application can be accepted.
     */
    public function canBeAccepted(): bool
    {
        return $this->isPending();
    }

    /**
     * Check if the application can be declined.
     */
    public function canBeDeclined(): bool
    {
        return $this->isPending();
    }

    /**
     * Check if the application can be withdrawn.
     */
    public function canBeWithdrawn(): bool
    {
        return $this->isPending();
    }

    /**
     * Check if the applicant is a business user.
     */
    public function isApplicantBusiness(): bool
    {
        return $this->applicant_profile_type === UserType::Business;
    }

    /**
     * Check if the applicant is a community user.
     */
    public function isApplicantCommunity(): bool
    {
        return $this->applicant_profile_type === UserType::Community;
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $collaboration_id
 * @property string $reviewer_profile_id
 * @property string $reviewer_role
 * @property int|null $rating
 * @property string|null $note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Collaboration $collaboration
 * @property-read Profile $reviewerProfile
 */
class CollaborationReview extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'collaboration_id',
        'reviewer_profile_id',
        'reviewer_role',
        'rating',
        'note',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    /**
     * Get the collaboration this review belongs to.
     *
     * @return BelongsTo<Collaboration, $this>
     */
    public function collaboration(): BelongsTo
    {
        return $this->belongsTo(Collaboration::class);
    }

    /**
     * Get the profile that submitted this review.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function reviewerProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'reviewer_profile_id');
    }
}

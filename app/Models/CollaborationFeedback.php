<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rich, role-specific completion feedback for a collaboration
 * (ROLES-AND-PERMISSIONS.md §4). One row per (collaboration, reviewer).
 *
 * @property string $id
 * @property string $collaboration_id
 * @property string $reviewer_profile_id
 * @property string $reviewer_type
 * @property string $reviewer_role
 * @property int $rating
 * @property int|null $posts_reels
 * @property bool|null $expectation_match
 * @property bool|null $would_recommend
 * @property bool|null $would_collaborate_again
 * @property int|null $stories_posted
 * @property string|null $revenue
 * @property string|null $benefits
 * @property bool $mirrored_from_review
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Collaboration $collaboration
 * @property-read Profile $reviewerProfile
 */
class CollaborationFeedback extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * Explicit table name (Laravel would otherwise pluralise to
     * collaboration_feedbacks).
     *
     * @var string
     */
    protected $table = 'collaboration_feedback';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'collaboration_id',
        'reviewer_profile_id',
        'reviewer_type',
        'reviewer_role',
        'rating',
        'posts_reels',
        'expectation_match',
        'would_recommend',
        'would_collaborate_again',
        'stories_posted',
        'revenue',
        'benefits',
        'mirrored_from_review',
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
            'posts_reels' => 'integer',
            'expectation_match' => 'boolean',
            'would_recommend' => 'boolean',
            'would_collaborate_again' => 'boolean',
            'stories_posted' => 'integer',
            'revenue' => 'decimal:2',
            'mirrored_from_review' => 'boolean',
        ];
    }

    /**
     * Cross-visible subset of the feedback that the partner can see once both
     * rows exist. See ROLES-AND-PERMISSIONS.md §4 (Q10 in the design plan).
     *
     * @return array<string, mixed>
     */
    public function publicSubset(): array
    {
        return [
            'rating' => $this->rating,
            'expectation_match' => $this->expectation_match,
            'would_recommend' => $this->would_recommend,
            'would_collaborate_again' => $this->would_collaborate_again,
        ];
    }

    /**
     * Get the collaboration this feedback belongs to.
     *
     * @return BelongsTo<Collaboration, $this>
     */
    public function collaboration(): BelongsTo
    {
        return $this->belongsTo(Collaboration::class);
    }

    /**
     * Get the profile that submitted this feedback.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function reviewerProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'reviewer_profile_id');
    }
}

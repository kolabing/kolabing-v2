<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\CollaborationReviewObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $collaboration_id
 * @property string $reviewer_profile_id
 * @property string $reviewer_role
 * @property string|null $reviewed_profile_id
 * @property int|null $rating
 * @property int|null $communication_rating
 * @property int|null $reliability_rating
 * @property int|null $fit_rating
 * @property int|null $value_rating
 * @property int|null $repeat_rating
 * @property string|null $note
 * @property string|null $body
 * @property string|null $public_comment
 * @property bool $public_comment_visible
 * @property bool|null $would_collaborate_again
 * @property-read float|null $overall_rating
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Collaboration $collaboration
 * @property-read Profile $reviewerProfile
 * @property-read Profile $reviewer
 * @property-read Profile $reviewed
 */
#[ObservedBy([CollaborationReviewObserver::class])]
class CollaborationReview extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The five star-rating columns introduced for the new review format.
     *
     * @var list<string>
     */
    public const STAR_RATING_FIELDS = [
        'communication_rating',
        'reliability_rating',
        'fit_rating',
        'value_rating',
        'repeat_rating',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'collaboration_id',
        'reviewer_profile_id',
        'reviewed_profile_id',
        'reviewer_role',
        'rating',
        'communication_rating',
        'reliability_rating',
        'fit_rating',
        'value_rating',
        'repeat_rating',
        'note',
        'body',
        'public_comment',
        'public_comment_visible',
        'would_collaborate_again',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'communication_rating' => 'integer',
            'reliability_rating' => 'integer',
            'fit_rating' => 'integer',
            'value_rating' => 'integer',
            'repeat_rating' => 'integer',
            'would_collaborate_again' => 'boolean',
            'public_comment_visible' => 'boolean',
        ];
    }

    /**
     * Average of the five new star ratings when present, falling back to the
     * legacy single `rating` field for reviews submitted before this format.
     */
    public function getOverallRatingAttribute(): ?float
    {
        $starRatings = array_filter(
            array_map(fn (string $field) => $this->{$field}, self::STAR_RATING_FIELDS),
            fn ($value) => $value !== null,
        );

        if (count($starRatings) === count(self::STAR_RATING_FIELDS)) {
            return round(array_sum($starRatings) / count($starRatings), 2);
        }

        return $this->rating !== null ? (float) $this->rating : null;
    }

    /**
     * SQL expression that yields each review's overall rating — the average of
     * the five star columns when all are present, otherwise the legacy `rating`.
     * Mirrors {@see getOverallRatingAttribute()} so aggregate queries (admin
     * stats, per-kolab averages) score star reviews precisely instead of the
     * rounded legacy fallback. Built from the {@see STAR_RATING_FIELDS} constant
     * — no user input — so it is safe to interpolate into a raw expression.
     */
    public static function overallRatingSqlExpression(): string
    {
        $sum = implode(' + ', self::STAR_RATING_FIELDS);

        return '(COALESCE(('.$sum.') / '.count(self::STAR_RATING_FIELDS).'.0, rating))';
    }

    /**
     * @return BelongsTo<Collaboration, $this>
     */
    public function collaboration(): BelongsTo
    {
        return $this->belongsTo(Collaboration::class);
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function reviewerProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'reviewer_profile_id');
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'reviewer_profile_id');
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function reviewed(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'reviewed_profile_id');
    }
}

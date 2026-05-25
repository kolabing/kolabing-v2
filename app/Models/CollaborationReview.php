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
 * @property string|null $reviewed_profile_id
 * @property int|null $rating
 * @property string|null $note
 * @property string|null $body
 * @property bool|null $would_collaborate_again
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Collaboration $collaboration
 * @property-read Profile $reviewerProfile
 * @property-read Profile $reviewer
 * @property-read Profile $reviewed
 */
class CollaborationReview extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'collaboration_id',
        'reviewer_profile_id',
        'reviewed_profile_id',
        'reviewer_role',
        'rating',
        'note',
        'body',
        'would_collaborate_again',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'would_collaborate_again' => 'boolean',
        ];
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

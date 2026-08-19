<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SuggestionAudience;
use App\Enums\SuggestionConfidence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A generated collaboration suggestion shown to one side of a proposed pair.
 * Rows are produced by app:generate-suggestions — one per pair, refreshed in
 * place — and are only ever read back by the profile named in
 * `viewer_profile_id` (see KolabSuggestionPolicy).
 */
class KolabSuggestion extends Model
{
    /** @use HasFactory<\Database\Factories\KolabSuggestionFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'audience',
        'viewer_profile_id',
        'counterpart_profile_id',
        'city_id',
        'score',
        'confidence',
        'signals',
        'suggested_format',
        'evidence',
        'batch_key',
        'expires_at',
        'shown_at',
        'clicked_at',
        'dismissed_at',
        'converted_kolab_id',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'audience' => SuggestionAudience::class,
            'score' => 'integer',
            'confidence' => SuggestionConfidence::class,
            'signals' => 'array',
            'suggested_format' => 'array',
            'evidence' => 'array',
            'batch_key' => 'date',
            'expires_at' => 'datetime',
            'shown_at' => 'datetime',
            'clicked_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function viewerProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'viewer_profile_id');
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function counterpartProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'counterpart_profile_id');
    }

    /**
     * Live = not expired, not dismissed, not already converted.
     *
     * @param  Builder<KolabSuggestion>  $query
     * @return Builder<KolabSuggestion>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereNull('dismissed_at')
            ->whereNull('converted_kolab_id')
            ->where('expires_at', '>', now());
    }

    /**
     * Restrict to the rows addressed to one profile. Every read path is
     * required to go through this scope: a suggestion names a counterpart, so
     * serving another profile's rows would leak who was matched with whom.
     *
     * @param  Builder<KolabSuggestion>  $query
     * @return Builder<KolabSuggestion>
     */
    public function scopeForViewer(Builder $query, Profile $viewer): Builder
    {
        return $query->where('viewer_profile_id', $viewer->id);
    }
}

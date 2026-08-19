<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single public endorsement ("vouch") of a listed community. One-directional by
 * design: there is no public downvote. Deduped by a PII-free hash so a vouch count is
 * always real taps, never seeded, and never reorders the editorial ranking.
 */
class ListingVouch extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'listing_id', 'ranking_city', 'dedupe_hash', 'verified', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CrmAccount, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'listing_id');
    }
}

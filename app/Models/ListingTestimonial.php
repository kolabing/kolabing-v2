<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member testimonial for a listed community. Moderation-first: created with
 * status=pending and never shown publicly until approved in /admin/rankings. Stores an
 * email_hash (not the raw email) for right-to-erasure and verified-member matching.
 */
class ListingTestimonial extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'listing_id', 'body', 'author_label', 'email_hash', 'verified_member',
        'status', 'reviewed_at', 'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'verified_member' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CrmAccount, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'listing_id');
    }

    /**
     * @param  Builder<ListingTestimonial>  $query
     * @return Builder<ListingTestimonial>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }
}

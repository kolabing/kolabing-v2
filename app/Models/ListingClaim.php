<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A community's "claim your listing" lead capture from a public ranking page.
 * Kept deliberately light (one honest form field beyond the name) — the free
 * listing is never gated behind a sales call.
 */
class ListingClaim extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'community_name', 'handle', 'email', 'city', 'source', 'crm_account_id',
    ];

    /**
     * @return BelongsTo<CrmAccount, $this>
     */
    public function crmAccount(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class);
    }
}

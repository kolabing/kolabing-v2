<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable event on a CRM account's timeline (stage move, note, first-touch,
 * contact). Written by CrmPipelineService; read by the account detail view.
 */
class CrmActivity extends Model
{
    /** @use HasFactory<\Database\Factories\CrmActivityFactory> */
    use HasFactory;

    use HasUuids;

    public const TYPES = ['note', 'stage_change', 'first_touch', 'contact'];

    protected $fillable = ['crm_account_id', 'type', 'actor', 'body', 'meta'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    /**
     * @return BelongsTo<CrmAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'crm_account_id');
    }
}

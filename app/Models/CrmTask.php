<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Admin task on two axes: area (sales|marketing|dev) × subarea
 * (business|communities|ambassadors|community_members).
 */
class CrmTask extends Model
{
    /** @use HasFactory<\Database\Factories\CrmTaskFactory> */
    use HasFactory;

    use HasUuids;

    public const AREAS = ['sales', 'marketing', 'dev'];

    public const SUBAREAS = ['business', 'communities', 'ambassadors', 'community_members'];

    public const STATUSES = ['open', 'doing', 'done'];

    /** ambassadors tasks only make sense under sales/marketing. */
    public const SUBAREA_AREA_RULES = ['ambassadors' => ['sales', 'marketing']];

    protected $fillable = [
        'title', 'crm_account_id', 'assignee', 'area', 'subarea',
        'due_on', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return ['due_on' => 'date'];
    }

    /**
     * @return BelongsTo<CrmAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'crm_account_id');
    }
}

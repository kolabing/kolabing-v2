<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CRM account (business | community | ambassador). See migration for the model.
 */
class CrmAccount extends Model
{
    /** @use HasFactory<\Database\Factories\CrmAccountFactory> */
    use HasFactory;

    use HasUuids;

    public const TYPES = ['business', 'community', 'ambassador'];

    protected $fillable = [
        'type', 'name', 'status', 'owner', 'email', 'phone',
        'instagram_handle', 'whatsapp', 'next_action', 'notes',
        'score', 'listed', 'metrics', 'linked_profile_id', 'last_activity_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metrics' => 'array',
            'score' => 'integer',
            'last_activity_at' => 'date',
        ];
    }

    /**
     * @return HasMany<CrmTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(CrmTask::class);
    }

    /** Days since last activity (null when never contacted). */
    public function daysSinceActivity(): ?int
    {
        return $this->last_activity_at === null
            ? null
            : (int) $this->last_activity_at->diffInDays(now());
    }

    /** Follow-up flag: no contact in 14+ days (the CRM rule). */
    public function needsFollowUp(): bool
    {
        $days = $this->daysSinceActivity();

        return $days !== null && $days >= 14;
    }

    /** Trial-Kolab candidate: warm pipeline + high score (business rule). */
    public function isTrialCandidate(): bool
    {
        return in_array($this->status, ['Interested', 'Active'], true) && $this->score > 80;
    }
}

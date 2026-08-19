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

    /**
     * Supply-side community pipeline. The forward funnel plus a terminal
     * Rejected/Dead lane. The account's `status` holds its current stage.
     */
    public const COMMUNITY_STAGES = ['Target', 'Contacted', 'Interested', 'Negotiating', 'Onboarded', 'Rejected'];

    public const COMMUNITY_FORWARD_STAGES = ['Target', 'Contacted', 'Interested', 'Negotiating', 'Onboarded'];

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

    /**
     * @return HasMany<CrmActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class)->latest();
    }

    /** Current pipeline stage (defaults to the funnel entry). */
    public function currentStage(): string
    {
        return in_array($this->status, self::COMMUNITY_STAGES, true) ? $this->status : 'Target';
    }

    /** The next forward stage, or null at Onboarded / when terminal (Rejected). */
    public function nextStage(): ?string
    {
        $i = array_search($this->currentStage(), self::COMMUNITY_FORWARD_STAGES, true);

        return ($i === false || $i >= count(self::COMMUNITY_FORWARD_STAGES) - 1)
            ? null
            : self::COMMUNITY_FORWARD_STAGES[$i + 1];
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

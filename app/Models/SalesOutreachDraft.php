<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One sales pitch (BE-NF-65): which business, which community, the generated Kolab
 * ideas, the chosen one, its cover image, the revenue arithmetic and the copy.
 *
 * Persisted from the moment of generation so a pitch survives review, editing and
 * re-preview without paying OpenAI again — and so what was actually sent to a real
 * business stays auditable afterwards.
 *
 * @property-read list<array<string, string>> $kolab_ideas
 */
class SalesOutreachDraft extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    /** Cover generation is asynchronous (BE-FX-61); these are its states. */
    public const IMAGE_IDLE = 'idle';

    public const IMAGE_PENDING = 'pending';

    public const IMAGE_READY = 'ready';

    public const IMAGE_FAILED = 'failed';

    protected $fillable = [
        'business_profile_id',
        'community_profile_id',
        'locale',
        'kolab_ideas',
        'selected_idea_index',
        'cover_image_url',
        'cover_image_status',
        'cover_image_error',
        'expected_attendees',
        'avg_spend_cents',
        'estimated_revenue_cents',
        'subject',
        'body_markdown',
        'status',
        'sent_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kolab_ideas' => 'array',
            'selected_idea_index' => 'integer',
            'expected_attendees' => 'integer',
            'avg_spend_cents' => 'integer',
            'estimated_revenue_cents' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'business_profile_id');
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'community_profile_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The idea this pitch is actually about.
     *
     * Falls back to the first rather than returning null: `selected_idea_index` is
     * user input, and a draft whose index drifted out of range should still render a
     * pitch a maintainer can read and fix, not a blank screen.
     *
     * @return array<string, string>|null
     */
    public function selectedIdea(): ?array
    {
        $ideas = $this->kolab_ideas;

        if (! is_array($ideas) || $ideas === []) {
            return null;
        }

        return $ideas[$this->selected_idea_index] ?? $ideas[0];
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isCoverPending(): bool
    {
        return $this->cover_image_status === self::IMAGE_PENDING;
    }

    public function coverFailed(): bool
    {
        return $this->cover_image_status === self::IMAGE_FAILED;
    }
}

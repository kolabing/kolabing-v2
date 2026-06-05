<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChatParticipantState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $thread_id
 * @property string $profile_id
 * @property ChatParticipantState $state
 * @property \Illuminate\Support\Carbon|null $joined_at
 * @property \Illuminate\Support\Carbon|null $banned_at
 * @property string|null $banned_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read ChatThread $thread
 * @property-read Profile $profile
 */
class ChatThreadParticipant extends Model
{
    /** @use HasFactory<\Database\Factories\ChatThreadParticipantFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'thread_id',
        'profile_id',
        'state',
        'joined_at',
        'banned_at',
        'banned_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => ChatParticipantState::class,
            'joined_at' => 'datetime',
            'banned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ChatThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'thread_id');
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }
}

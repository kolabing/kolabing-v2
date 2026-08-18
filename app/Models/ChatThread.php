<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChatThreadType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property ChatThreadType $type
 * @property string|null $application_id
 * @property string|null $community_id
 * @property string|null $event_id
 * @property string|null $slug
 * @property string|null $name
 * @property string|null $created_by
 * @property \Illuminate\Support\Carbon|null $last_message_at
 * @property int $unread_count Transient, set by ChatService for the active-chats list.
 * @property-read Application|null $application
 */
class ChatThread extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'application_id',
        'community_id',
        'event_id',
        'series_id',
        'slug',
        'name',
        'created_by',
        'last_message_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ChatThreadType::class,
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'thread_id');
    }

    /**
     * The most recent message, for the chat-list preview (#8).
     *
     * Deliberately NOT `latestOfMany()`/`ofMany()`: those always add
     * `MAX(<primary key>)` to the sub-query (`CanBeOneOfMany::ofMany()` forces the
     * key in), and `chat_messages.id` is a `uuid` — Postgres has no `max(uuid)`, so
     * eager-loading it turned `GET /chats` into a hard 500 in production (#146).
     * SQLite (what the test suite runs on) evaluates `MAX(<uuid>)` happily, which is
     * why CI never saw it.
     *
     * Lazy access is cheap (`HasOne` resolves with `first()`, i.e. ORDER BY +
     * LIMIT 1). Do NOT eager-load this across a thread list — without `ofMany` that
     * would pull every message of every thread; use
     * `ChatService::attachLatestMessages()`, which does it in two grouped queries.
     *
     * @return HasOne<ChatMessage, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class, 'thread_id')->latest('chat_messages.created_at');
    }

    /**
     * @return HasMany<ChatThreadRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(ChatThreadRead::class, 'thread_id');
    }
}

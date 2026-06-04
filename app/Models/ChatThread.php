<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChatThreadType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
 * @property int $unread_count  Transient, set by ChatService for the active-chats list.
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
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'thread_id');
    }

    /**
     * @return HasMany<ChatThreadRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(ChatThreadRead::class, 'thread_id');
    }
}

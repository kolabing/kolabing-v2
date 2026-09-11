<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventSignupStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $event_id
 * @property string $profile_id
 * @property EventSignupStatus $status
 * @property int|null $waitlist_position
 * @property string|null $ticket_code
 * @property \Illuminate\Support\Carbon|null $ticket_issued_at
 * @property \Illuminate\Support\Carbon|null $ticket_emailed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Event $event
 * @property-read Profile $profile
 */
class EventSignup extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'profile_id',
        'status',
        'waitlist_position',
        'ticket_code',
        'ticket_issued_at',
        'ticket_emailed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EventSignupStatus::class,
            'waitlist_position' => 'integer',
            'ticket_issued_at' => 'datetime',
            'ticket_emailed_at' => 'datetime',
        ];
    }

    /** Whether this sign-up holds a seat that has been turned into a ticket. */
    public function hasTicket(): bool
    {
        return $this->ticket_code !== null && $this->status === EventSignupStatus::Going;
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}

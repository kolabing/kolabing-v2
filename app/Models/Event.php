<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $profile_id
 * @property string $name
 * @property string $partner_name
 * @property string $partner_type
 * @property \Illuminate\Support\Carbon $event_date
 * @property int $attendee_count
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $profile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventPhoto> $photos
 */
class Event extends Model
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'profile_id',
        'name',
        'partner_name',
        'partner_type',
        'event_date',
        'attendee_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'attendee_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * @return HasMany<EventPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(EventPhoto::class)->orderBy('sort_order');
    }
}

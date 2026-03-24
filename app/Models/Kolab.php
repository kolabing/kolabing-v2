<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IntentType;
use App\Enums\KolabStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $creator_profile_id
 * @property IntentType $intent_type
 * @property KolabStatus $status
 * @property string $title
 * @property string $description
 * @property string $preferred_city
 * @property string|null $area
 * @property array<string, mixed>|null $media
 * @property string|null $availability_mode
 * @property \Illuminate\Support\Carbon|null $availability_start
 * @property \Illuminate\Support\Carbon|null $availability_end
 * @property string|null $selected_time
 * @property array<int>|null $recurring_days
 * @property array<string, mixed>|null $needs
 * @property array<string>|null $community_types
 * @property int|null $community_size
 * @property int|null $typical_attendance
 * @property array<string, mixed>|null $offers_in_return
 * @property string|null $venue_preference
 * @property string|null $venue_name
 * @property string|null $venue_type
 * @property int|null $capacity
 * @property string|null $venue_address
 * @property string|null $product_name
 * @property string|null $product_type
 * @property array<string, mixed>|null $offering
 * @property array<string, mixed>|null $seeking_communities
 * @property int|null $min_community_size
 * @property array<string, mixed>|null $expects
 * @property array<string, mixed>|null $past_events
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $creatorProfile
 */
class Kolab extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'kolabs';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'creator_profile_id',
        'intent_type',
        'status',
        'title',
        'description',
        'preferred_city',
        'area',
        'media',
        'availability_mode',
        'availability_start',
        'availability_end',
        'selected_time',
        'recurring_days',
        'needs',
        'community_types',
        'community_size',
        'typical_attendance',
        'offers_in_return',
        'venue_preference',
        'venue_name',
        'venue_type',
        'capacity',
        'venue_address',
        'product_name',
        'product_type',
        'offering',
        'seeking_communities',
        'min_community_size',
        'expects',
        'past_events',
        'published_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'intent_type' => IntentType::class,
            'status' => KolabStatus::class,
            'media' => 'array',
            'availability_start' => 'date',
            'availability_end' => 'date',
            'recurring_days' => 'array',
            'needs' => 'array',
            'community_types' => 'array',
            'offers_in_return' => 'array',
            'offering' => 'array',
            'seeking_communities' => 'array',
            'expects' => 'array',
            'past_events' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Get the profile that created this kolab.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function creatorProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'creator_profile_id');
    }

    /**
     * Scope a query to only include published kolabs.
     *
     * @param  Builder<Kolab>  $query
     * @return Builder<Kolab>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', KolabStatus::Published);
    }

    /**
     * Scope a query to filter kolabs by city.
     *
     * @param  Builder<Kolab>  $query
     * @return Builder<Kolab>
     */
    public function scopeForCity(Builder $query, string $city): Builder
    {
        return $query->where('preferred_city', $city);
    }

    /**
     * Scope a query to filter kolabs by intent type.
     *
     * @param  Builder<Kolab>  $query
     * @return Builder<Kolab>
     */
    public function scopeByIntent(Builder $query, IntentType $intentType): Builder
    {
        return $query->where('intent_type', $intentType);
    }

    /**
     * Check if the kolab is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === KolabStatus::Draft;
    }

    /**
     * Check if the kolab is published.
     */
    public function isPublished(): bool
    {
        return $this->status === KolabStatus::Published;
    }

    /**
     * Check if the kolab is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === KolabStatus::Closed;
    }
}

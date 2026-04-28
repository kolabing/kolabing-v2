<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $profile_id
 * @property string|null $name
 * @property string|null $about
 * @property string|null $business_type
 * @property array<int, string>|null $categories
 * @property string|null $city_id
 * @property string|null $city_name
 * @property string|null $city_country
 * @property string|null $instagram
 * @property string|null $website
 * @property string|null $profile_photo
 * @property array<string, mixed>|null $primary_venue
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $profile
 * @property-read City|null $city
 */
class BusinessProfile extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'profile_id',
        'name',
        'about',
        'business_type',
        'categories',
        'city_id',
        'city_name',
        'city_country',
        'instagram',
        'website',
        'profile_photo',
        'primary_venue',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'primary_venue' => 'array',
        ];
    }

    /**
     * Get the ordered business categories with legacy fallback.
     *
     * @return array<int, string>
     */
    public function normalizedCategories(): array
    {
        $categories = is_array($this->categories)
            ? array_values(array_filter($this->categories, static fn (mixed $value): bool => is_string($value) && $value !== ''))
            : [];

        if ($categories !== []) {
            return $categories;
        }

        return $this->business_type ? [$this->business_type] : [];
    }

    /**
     * Get the compatibility primary business type.
     */
    public function primaryCategory(): ?string
    {
        return $this->normalizedCategories()[0] ?? $this->business_type;
    }

    /**
     * Get the profile that owns this business profile.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Get the city where this business is located.
     *
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $country
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class City extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'country',
    ];

    /**
     * Get the business profiles located in this city.
     *
     * @return HasMany<BusinessProfile, $this>
     */
    public function businessProfiles(): HasMany
    {
        return $this->hasMany(BusinessProfile::class);
    }

    /**
     * Get the community profiles located in this city.
     *
     * @return HasMany<CommunityProfile, $this>
     */
    public function communityProfiles(): HasMany
    {
        return $this->hasMany(CommunityProfile::class);
    }
}

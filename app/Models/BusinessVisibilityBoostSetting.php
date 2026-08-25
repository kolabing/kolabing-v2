<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row settings model controlling the discovery visibility boost
 * points for Trusted Partner / Community Favourite businesses.
 *
 * @property string $id
 * @property int $trusted_partner_points
 * @property int $community_favourite_points
 */
class BusinessVisibilityBoostSetting extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'business_visibility_boost_settings';

    /** @var list<string> */
    protected $fillable = [
        'trusted_partner_points',
        'community_favourite_points',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'trusted_partner_points' => 'integer',
            'community_favourite_points' => 'integer',
        ];
    }
}

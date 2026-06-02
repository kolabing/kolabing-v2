<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GamificationBadgeSlug;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-editable override for an enum-backed badge (System B). Any non-null
 * column wins over the enum default; null columns fall back to the enum
 * methods. See GamificationBadgeMetadataService.
 *
 * @property string $id
 * @property GamificationBadgeSlug $badge_slug
 * @property string|null $name
 * @property string|null $description
 * @property string|null $icon
 * @property array<int, string>|null $audiences
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class GamificationBadgeOverride extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'badge_slug',
        'name',
        'description',
        'icon',
        'audiences',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'badge_slug' => GamificationBadgeSlug::class,
            'audiences' => 'array',
        ];
    }
}

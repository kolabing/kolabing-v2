<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Global partner reward, redeemable with global XP. Admin-managed later;
 * read-only via the app for now.
 *
 * @property string $id
 * @property string $title
 * @property string $partner
 * @property string|null $description
 * @property string|null $image_url
 * @property int $cost_xp
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class PartnerReward extends Model
{
    /** @use HasFactory<\Database\Factories\PartnerRewardFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'title',
        'partner',
        'description',
        'image_url',
        'cost_xp',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cost_xp' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}

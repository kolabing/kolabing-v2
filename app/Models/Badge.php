<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BadgeMilestoneType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $description
 * @property string $icon
 * @property BadgeMilestoneType $milestone_type
 * @property int $milestone_value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, BadgeAward> $awards
 */
class Badge extends Model
{
    /** @use HasFactory<\Database\Factories\BadgeFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'icon',
        'milestone_type',
        'milestone_value',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'milestone_type' => BadgeMilestoneType::class,
            'milestone_value' => 'integer',
        ];
    }

    /**
     * @return HasMany<BadgeAward, $this>
     */
    public function awards(): HasMany
    {
        return $this->hasMany(BadgeAward::class);
    }
}

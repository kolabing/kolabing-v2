<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PointEventType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $event_type
 * @property int $points
 * @property string $label
 * @property bool $is_active
 * @property int $position
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class XpEarnRule extends Model
{
    /** @use HasFactory<\Database\Factories\XpEarnRuleFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'event_type',
        'points',
        'label',
        'is_active',
        'position',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * Try to map this row back to the PointEventType enum case; null if the
     * stored event_type slug isn't a known case.
     */
    public function pointEventType(): ?PointEventType
    {
        return PointEventType::tryFrom($this->event_type);
    }
}

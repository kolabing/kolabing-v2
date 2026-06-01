<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $number
 * @property string $title
 * @property int $min_xp
 * @property int|null $max_xp Null only on the open-ended top level.
 * @property string $color
 * @property int $position
 */
class XpLevel extends Model
{
    /** @use HasFactory<\Database\Factories\XpLevelFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'number',
        'title',
        'min_xp',
        'max_xp',
        'color',
        'position',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'min_xp' => 'integer',
            'max_xp' => 'integer',
            'position' => 'integer',
        ];
    }
}

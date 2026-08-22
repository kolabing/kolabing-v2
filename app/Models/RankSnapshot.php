<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A weekly capture of a community's rank in a city, so "up N this week" movement on the
 * public pages is a real delta between snapshots, never invented.
 */
class RankSnapshot extends Model
{
    use HasFactory;
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'listing_id', 'city', 'rank', 'captured_on',
    ];

    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'captured_on' => 'date',
        ];
    }
}

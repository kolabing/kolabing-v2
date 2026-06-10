<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-adjustable scoring weight (business fit factor or ambassador point).
 */
class CrmScoreWeight extends Model
{
    use HasUuids;

    protected $fillable = ['applies_to', 'key', 'label', 'points'];

    protected function casts(): array
    {
        return ['points' => 'integer'];
    }
}

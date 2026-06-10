<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-admin column visibility for a CRM table.
 */
class AdminColumnPref extends Model
{
    use HasUuids;

    protected $fillable = ['admin_id', 'table_key', 'visible_columns'];

    protected function casts(): array
    {
        return ['visible_columns' => 'array'];
    }
}

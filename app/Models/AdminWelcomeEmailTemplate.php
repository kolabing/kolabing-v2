<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $locale
 * @property string $label
 * @property string $subject
 * @property string $intro_markdown
 * @property string $next_steps_markdown
 * @property string $footer_markdown
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AdminWelcomeEmailTemplate extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'locale',
        'label',
        'subject',
        'intro_markdown',
        'next_steps_markdown',
        'footer_markdown',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}

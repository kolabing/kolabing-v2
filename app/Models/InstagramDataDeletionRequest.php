<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A Meta data-deletion callback we received and carried out; its confirmation
 * code is what Meta shows the user, and /instagram/data-deletion/{code} reports it.
 *
 * @property string $id
 * @property string $confirmation_code
 * @property string $ig_user_id
 * @property string $status
 * @property int $accounts_deleted
 * @property int $media_deleted
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class InstagramDataDeletionRequest extends Model
{
    use HasUuids;

    public const STATUS_COMPLETED = 'completed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'confirmation_code',
        'ig_user_id',
        'status',
        'accounts_deleted',
        'media_deleted',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accounts_deleted' => 'integer',
            'media_deleted' => 'integer',
            'completed_at' => 'datetime',
        ];
    }
}

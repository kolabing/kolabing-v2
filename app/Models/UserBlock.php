<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $blocker_profile_id
 * @property string $blocked_profile_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $blocker
 * @property-read Profile $blocked
 */
class UserBlock extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'user_blocks';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'blocker_profile_id',
        'blocked_profile_id',
    ];

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function blocker(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'blocker_profile_id');
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function blocked(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'blocked_profile_id');
    }
}

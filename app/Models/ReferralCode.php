<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $profile_id
 * @property string $code
 * @property int $total_conversions
 * @property int $total_points_earned
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $profile
 */
class ReferralCode extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'profile_id',
        'code',
        'total_conversions',
        'total_points_earned',
    ];

    protected function casts(): array
    {
        return [
            'total_conversions' => 'integer',
            'total_points_earned' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public static function generateCode(): string
    {
        do {
            $code = 'KOLAB-'.strtoupper(Str::random(4));
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }
}

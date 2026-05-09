<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $profile_id
 * @property string $code
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property int $total_conversions
 * @property int $total_points_earned
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $profile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ReferralRedemption> $redemptions
 */
class ReferralCode extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'profile_id',
        'code',
        'expires_at',
        'total_conversions',
        'total_points_earned',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
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

    /**
     * @return HasMany<ReferralRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(ReferralRedemption::class);
    }

    public static function generateCode(): string
    {
        do {
            $code = 'KOLAB-'.strtoupper(Str::random(4));
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }
}

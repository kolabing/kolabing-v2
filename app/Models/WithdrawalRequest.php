<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WithdrawalStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $profile_id
 * @property int $points
 * @property float $eur_amount
 * @property string $iban
 * @property string $account_holder
 * @property WithdrawalStatus $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $profile
 */
class WithdrawalRequest extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'profile_id',
        'points',
        'eur_amount',
        'iban',
        'account_holder',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'eur_amount' => 'decimal:2',
            'status' => WithdrawalStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function getMaskedIban(): string
    {
        $length = strlen($this->iban);
        if ($length <= 8) {
            return $this->iban;
        }

        return substr($this->iban, 0, 4).str_repeat('*', $length - 8).substr($this->iban, -4);
    }
}

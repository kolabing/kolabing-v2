<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $suggested_by
 * @property string $city_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $suggestedByProfile
 */
class CitySuggestion extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'suggested_by',
        'city_name',
    ];

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function suggestedByProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'suggested_by');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MultiKolabCompensationType;
use App\Enums\MultiKolabEligibleAccountType;
use App\Enums\MultiKolabRoleStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One recruitable partner slot on a {@see MultiKolabEvent}.
 *
 * @property string $id
 * @property string $multi_kolab_event_id
 * @property MultiKolabRoleStatus $status
 * @property string $title
 * @property MultiKolabEligibleAccountType $eligible_account_type
 * @property int $positions_needed
 * @property int $positions_filled
 * @property bool $required
 * @property string|null $need
 * @property string|null $receive
 * @property MultiKolabCompensationType|null $compensation_type
 * @property string|null $requirements
 * @property string|null $details
 * @property-read MultiKolabEvent $event
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MultiKolabRoleApplication> $applications
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Kolab> $kolabs
 */
class MultiKolabRole extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'multi_kolab_event_id',
        'status',
        'title',
        'eligible_account_type',
        'positions_needed',
        'positions_filled',
        'required',
        'need',
        'receive',
        'compensation_type',
        'requirements',
        'details',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MultiKolabRoleStatus::class,
            'eligible_account_type' => MultiKolabEligibleAccountType::class,
            'compensation_type' => MultiKolabCompensationType::class,
            'positions_needed' => 'integer',
            'positions_filled' => 'integer',
            'required' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<MultiKolabEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(MultiKolabEvent::class, 'multi_kolab_event_id');
    }

    /**
     * @return HasMany<MultiKolabRoleApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(MultiKolabRoleApplication::class, 'multi_kolab_role_id');
    }

    /**
     * Child Kolabs created when an application to this role is accepted.
     *
     * @return HasMany<Kolab, $this>
     */
    public function kolabs(): HasMany
    {
        return $this->hasMany(Kolab::class, 'multi_kolab_role_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MultiKolabRoleApplicationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A free application from a Business or Community profile to a
 * {@see MultiKolabRole}. Acceptance (Task 6) links {@see kolab_id} to the
 * canonical child Kolab it produced.
 *
 * @property string $id
 * @property string $multi_kolab_role_id
 * @property string $applicant_profile_id
 * @property string $applicant_profile_type
 * @property MultiKolabRoleApplicationStatus $status
 * @property string|null $pitch
 * @property string|null $availability
 * @property string|null $withdrawal_reason
 * @property string|null $kolab_id
 * @property \Illuminate\Support\Carbon|null $accepted_at
 * @property \Illuminate\Support\Carbon|null $declined_at
 * @property \Illuminate\Support\Carbon|null $withdrawn_at
 * @property-read MultiKolabRole $role
 * @property-read Profile $applicantProfile
 * @property-read Kolab|null $kolab
 */
class MultiKolabRoleApplication extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'multi_kolab_role_id',
        'applicant_profile_id',
        'applicant_profile_type',
        'status',
        'pitch',
        'availability',
        'withdrawal_reason',
        'kolab_id',
        'accepted_at',
        'declined_at',
        'withdrawn_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MultiKolabRoleApplicationStatus::class,
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MultiKolabRole, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(MultiKolabRole::class, 'multi_kolab_role_id');
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function applicantProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'applicant_profile_id');
    }

    /**
     * The canonical child Kolab created when this application was accepted.
     * Null until acceptance (Task 6).
     *
     * @return BelongsTo<Kolab, $this>
     */
    public function kolab(): BelongsTo
    {
        return $this->belongsTo(Kolab::class, 'kolab_id');
    }
}

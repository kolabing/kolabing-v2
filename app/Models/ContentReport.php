<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $reporter_profile_id
 * @property string $target_type
 * @property string $target_id
 * @property string|null $reported_profile_id
 * @property string $reason
 * @property string|null $note
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $reporter
 * @property-read Profile|null $reportedProfile
 */
class ContentReport extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'content_reports';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reporter_profile_id',
        'target_type',
        'target_id',
        'reported_profile_id',
        'reason',
        'note',
        'status',
    ];

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'open',
    ];

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'reporter_profile_id');
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function reportedProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'reported_profile_id');
    }
}

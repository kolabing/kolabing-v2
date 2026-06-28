<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CollaborationCompletionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lightweight completion confirmation for a collaboration. One row per
 * (collaboration, profile). Decoupled from the rich CollaborationFeedback
 * table — this is the required step that gates /complete; feedback stays
 * optional impact data.
 *
 * @property string $id
 * @property string $collaboration_id
 * @property string $profile_id
 * @property string $role
 * @property CollaborationCompletionStatus $status
 * @property string|null $note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Collaboration $collaboration
 * @property-read Profile $profile
 */
class CollaborationCompletion extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'collaboration_completions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'collaboration_id',
        'profile_id',
        'role',
        'status',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CollaborationCompletionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Collaboration, $this>
     */
    public function collaboration(): BelongsTo
    {
        return $this->belongsTo(Collaboration::class);
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}

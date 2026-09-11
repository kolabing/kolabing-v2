<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What an applicant answered, attached to their join request
 * (kolabing-app#138).
 *
 * [$prompt_snapshot] records the wording at the time of answering. A leader can
 * reword a live question, and an application must always read as the applicant
 * saw it — retiring alone does not cover that.
 *
 * @property string $id
 * @property string $join_request_id
 * @property string $question_id
 * @property string|null $prompt_snapshot
 * @property string $answer
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read CommunityJoinRequest $joinRequest
 * @property-read CommunityJoinQuestion $question
 */
class CommunityJoinAnswer extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'join_request_id',
        'question_id',
        'prompt_snapshot',
        'answer',
    ];

    /**
     * @return BelongsTo<CommunityJoinRequest, $this>
     */
    public function joinRequest(): BelongsTo
    {
        return $this->belongsTo(CommunityJoinRequest::class, 'join_request_id');
    }

    /**
     * @return BelongsTo<CommunityJoinQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(CommunityJoinQuestion::class, 'question_id');
    }
}

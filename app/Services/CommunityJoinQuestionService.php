<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Community;
use App\Models\CommunityJoinQuestion;
use DomainException;
use Illuminate\Database\Eloquent\Collection;

/**
 * The questions a leader asks before admitting a member (kolabing-app#138).
 *
 * The cap and the retire-don't-delete rule live here rather than in the
 * controller, so they hold for any caller — a seeder, an admin tool, a future
 * endpoint — and not just for the one that happens to go through the API.
 */
class CommunityJoinQuestionService
{
    /**
     * The set an applicant is asked, in display order.
     *
     * @return Collection<int, CommunityJoinQuestion>
     */
    public function activeFor(Community $community): Collection
    {
        return CommunityJoinQuestion::query()
            ->where('community_id', $community->id)
            ->activeOrdered()
            ->get();
    }

    /**
     * @throws DomainException when the community already has the maximum number
     *                         of active questions
     */
    public function create(
        Community $community,
        string $prompt,
        bool $required = true,
        ?int $position = null
    ): CommunityJoinQuestion {
        $activeCount = CommunityJoinQuestion::query()
            ->where('community_id', $community->id)
            ->where('is_active', true)
            ->count();

        if ($activeCount >= CommunityJoinQuestion::MAX_ACTIVE) {
            throw new DomainException('too_many_questions');
        }

        return CommunityJoinQuestion::query()->create([
            'community_id' => $community->id,
            'position' => $position ?? ($activeCount + 1),
            'prompt' => $prompt,
            'required' => $required,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes  any of prompt, required, position
     */
    public function update(
        CommunityJoinQuestion $question,
        array $attributes
    ): CommunityJoinQuestion {
        $question->update(array_intersect_key($attributes, array_flip([
            'prompt', 'required', 'position',
        ])));

        return $question->fresh();
    }

    /**
     * Retire a question rather than delete it.
     *
     * Deleting would cascade its answers away, and a leader reviewing an older
     * application would be left with an answer to a question nobody can see.
     * Retiring takes it out of the form and leaves the record intact.
     */
    public function retire(CommunityJoinQuestion $question): CommunityJoinQuestion
    {
        $question->update(['is_active' => false]);

        return $question->fresh();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\UserType;
use App\Models\Community;
use App\Models\CommunityFollower;
use App\Models\CommunityJoinAnswer;
use App\Models\CommunityJoinQuestion;
use App\Models\CommunityJoinRequest;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CommunityFollowerRelationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function community(): Community
    {
        $owner = Profile::query()->create([
            'email' => 'leader-'.uniqid().'@example.test',
            'password' => 'secret1234',
            'user_type' => UserType::Community,
        ]);

        return Community::query()->create([
            'owner_profile_id' => $owner->id,
            'name' => 'Relations Test Club',
            'slug' => 'relations-'.uniqid(),
            'type' => 'running',
        ]);
    }

    private function follower(): Profile
    {
        return Profile::query()->create([
            'email' => 'follower-'.uniqid().'@example.test',
            'password' => 'secret1234',
            'user_type' => UserType::Attendee,
        ]);
    }

    public function test_a_community_has_followers_both_ways(): void
    {
        $community = $this->community();
        $profile = $this->follower();

        $follow = CommunityFollower::query()->create([
            'community_id' => $community->id,
            'profile_id' => $profile->id,
            'followed_at' => Carbon::now(),
        ]);

        $this->assertTrue($community->followers()->where('id', $follow->id)->exists());
        $this->assertTrue($profile->communityFollows()->where('id', $follow->id)->exists());
        $this->assertSame($community->id, $follow->community->id);
        $this->assertSame($profile->id, $follow->profile->id);
        $this->assertInstanceOf(Carbon::class, $follow->followed_at);
    }

    /**
     * Following is not membership. The whole point of the separate table is
     * that a follow leaves `community_members` alone.
     */
    public function test_following_creates_no_membership(): void
    {
        $community = $this->community();
        $profile = $this->follower();

        CommunityFollower::query()->create([
            'community_id' => $community->id,
            'profile_id' => $profile->id,
            'followed_at' => Carbon::now(),
        ]);

        $this->assertFalse(
            $community->members()->where('profile_id', $profile->id)->exists()
        );
    }

    public function test_questions_are_ordered_and_scopable_to_active(): void
    {
        $community = $this->community();

        $community->joinQuestions()->create([
            'position' => 2, 'prompt' => 'Second', 'required' => true, 'is_active' => true,
        ]);
        $community->joinQuestions()->create([
            'position' => 1, 'prompt' => 'First', 'required' => true, 'is_active' => true,
        ]);
        $community->joinQuestions()->create([
            'position' => 3, 'prompt' => 'Retired', 'required' => false, 'is_active' => false,
        ]);

        $prompts = $community->joinQuestions()->pluck('prompt')->all();
        $this->assertSame(['First', 'Second', 'Retired'], $prompts);

        $active = CommunityJoinQuestion::query()
            ->where('community_id', $community->id)
            ->activeOrdered()
            ->pluck('prompt')
            ->all();
        $this->assertSame(['First', 'Second'], $active);
    }

    public function test_a_join_request_carries_its_answers(): void
    {
        $community = $this->community();
        $applicant = $this->follower();

        $question = $community->joinQuestions()->create([
            'position' => 1, 'prompt' => 'Why join?', 'required' => true, 'is_active' => true,
        ]);

        $request = CommunityJoinRequest::query()->create([
            'community_id' => $community->id,
            'profile_id' => $applicant->id,
            'status' => 'pending',
            'requested_at' => Carbon::now(),
        ]);

        $answer = CommunityJoinAnswer::query()->create([
            'join_request_id' => $request->id,
            'question_id' => $question->id,
            'answer' => 'I run every Tuesday.',
        ]);

        $this->assertSame(1, $request->answers()->count());
        $this->assertSame('I run every Tuesday.', $request->answers()->first()->answer);
        $this->assertSame($question->id, $answer->question->id);
        $this->assertSame($request->id, $answer->joinRequest->id);
    }

    /**
     * Retiring a question must not take its answers with it — a leader
     * reviewing an old application still needs to see what was asked.
     */
    public function test_retiring_a_question_keeps_its_answers_readable(): void
    {
        $community = $this->community();
        $applicant = $this->follower();

        $question = $community->joinQuestions()->create([
            'position' => 1, 'prompt' => 'Why join?', 'required' => true, 'is_active' => true,
        ]);
        $request = CommunityJoinRequest::query()->create([
            'community_id' => $community->id,
            'profile_id' => $applicant->id,
            'status' => 'pending',
            'requested_at' => Carbon::now(),
        ]);
        CommunityJoinAnswer::query()->create([
            'join_request_id' => $request->id,
            'question_id' => $question->id,
            'answer' => 'Still readable.',
        ]);

        $question->update(['is_active' => false]);

        $this->assertSame(1, $request->fresh()->answers()->count());
        $this->assertSame(
            'Why join?',
            $request->answers()->first()->question->prompt
        );
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CommunityMemberStatus;
use App\Enums\JoinPolicy;
use App\Enums\JoinRequestStatus;
use App\Enums\UserType;
use App\Models\Community;
use App\Models\CommunityJoinAnswer;
use App\Models\CommunityJoinQuestion;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Applying for membership with answers (kolabing-app#138).
 *
 * The existing CommunityJoinRequestTest stays untouched as the regression guard
 * for the flow as it was; this covers what the questions layer adds.
 */
class CommunityJoinApplicationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Profile $leader;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->leader = Profile::query()->create([
            'email' => 'leader-'.uniqid().'@example.test',
            'password' => 'secret1234',
            'user_type' => UserType::Community,
        ]);
    }

    private function community(JoinPolicy $policy): Community
    {
        $community = Community::query()->create([
            'owner_profile_id' => $this->leader->id,
            'name' => 'Application Test Club',
            'slug' => 'apply-'.uniqid(),
            'type' => 'running',
            'join_policy' => $policy->value,
        ]);

        // A default tier, so approval produces the same member row a normal
        // join does.
        $community->tiers()->create([
            'name' => 'Member',
            'rank' => 1,
            'assignment_rule' => 'manual',
            'permissions' => ['view' => [], 'chat_channels' => [], 'perks' => [], 'capabilities' => []],
            'is_default' => true,
        ]);

        return $community;
    }

    private function ask(Community $community, string $prompt, bool $required = true, int $position = 1): CommunityJoinQuestion
    {
        return $community->joinQuestions()->create([
            'position' => $position,
            'prompt' => $prompt,
            'required' => $required,
            'is_active' => true,
        ]);
    }

    private function applicant(): Profile
    {
        return Profile::query()->create([
            'email' => 'applicant-'.uniqid().'@example.test',
            'password' => 'secret1234',
            'user_type' => UserType::Attendee,
        ]);
    }

    // -------------------------------------------------------------------------
    // invite_only: answers are stored and shown to the leader
    // -------------------------------------------------------------------------

    public function test_answers_are_stored_and_returned_to_the_leader(): void
    {
        $community = $this->community(JoinPolicy::InviteOnly);
        $q1 = $this->ask($community, 'Why do you want to join?', true, 1);
        $q2 = $this->ask($community, 'How far do you run?', false, 2);
        $applicant = $this->applicant();

        $this->actingAs($applicant)
            ->postJson("/api/v1/communities/{$community->id}/join-requests", [
                'answers' => [
                    ['question_id' => $q1->id, 'answer' => 'I moved here last month.'],
                    ['question_id' => $q2->id, 'answer' => '10k'],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', JoinRequestStatus::Pending->value);

        $this->assertSame(2, CommunityJoinAnswer::query()->count());

        $response = $this->actingAs($this->leader)
            ->getJson("/api/v1/communities/{$community->id}/join-requests");

        $response->assertStatus(200);
        $answers = collect($response->json('data.0.answers'));
        $this->assertCount(2, $answers);
        $this->assertSame('Why do you want to join?', $answers->firstWhere('question_id', $q1->id)['prompt']);
        $this->assertSame('I moved here last month.', $answers->firstWhere('question_id', $q1->id)['answer']);
    }

    public function test_a_required_question_left_blank_is_refused(): void
    {
        $community = $this->community(JoinPolicy::InviteOnly);
        $this->ask($community, 'Required one', true, 1);

        $this->actingAs($this->applicant())
            ->postJson("/api/v1/communities/{$community->id}/join-requests", ['answers' => []])
            ->assertStatus(422)
            ->assertJsonPath('error', 'missing_required_answers');

        $this->assertSame(0, CommunityJoinAnswer::query()->count());
    }

    /**
     * A blank answer is refused, and no request or answer row is left behind.
     *
     * It is the request validator that catches it rather than the service's
     * required-question check: Laravel's `required_with` treats a string that
     * trims to empty as absent, so `answers.*.answer` fails first. Both layers
     * reject it — this asserts the outcome rather than which one fired, so the
     * test does not break if the validation moves.
     */
    public function test_a_whitespace_only_answer_is_refused(): void
    {
        $community = $this->community(JoinPolicy::InviteOnly);
        $q = $this->ask($community, 'Required one', true, 1);

        $this->actingAs($this->applicant())
            ->postJson("/api/v1/communities/{$community->id}/join-requests", [
                'answers' => [['question_id' => $q->id, 'answer' => '   ']],
            ])
            ->assertStatus(422);

        $this->assertSame(0, CommunityJoinAnswer::query()->count());
        $this->assertSame(0, $community->joinRequests()->count());
    }

    public function test_an_optional_question_may_be_skipped(): void
    {
        $community = $this->community(JoinPolicy::InviteOnly);
        $this->ask($community, 'Optional', false, 1);

        $this->actingAs($this->applicant())
            ->postJson("/api/v1/communities/{$community->id}/join-requests", ['answers' => []])
            ->assertStatus(201);
    }

    /**
     * A client must not be able to smuggle in an answer to a question that does
     * not belong to this community.
     */
    public function test_a_foreign_question_id_is_ignored(): void
    {
        $community = $this->community(JoinPolicy::InviteOnly);
        $this->ask($community, 'Optional', false, 1);

        $other = $this->community(JoinPolicy::InviteOnly);
        $foreign = $this->ask($other, 'Elsewhere', false, 1);

        $this->actingAs($this->applicant())
            ->postJson("/api/v1/communities/{$community->id}/join-requests", [
                'answers' => [['question_id' => $foreign->id, 'answer' => 'sneaky']],
            ])
            ->assertStatus(201);

        $this->assertSame(0, CommunityJoinAnswer::query()->count());
    }

    // -------------------------------------------------------------------------
    // open + questions: the one behaviour change, and its guard
    // -------------------------------------------------------------------------

    public function test_an_open_community_with_questions_accepts_and_auto_approves(): void
    {
        $community = $this->community(JoinPolicy::Open);
        $q = $this->ask($community, 'Why join?', true, 1);
        $applicant = $this->applicant();

        $this->actingAs($applicant)
            ->postJson("/api/v1/communities/{$community->id}/join-requests", [
                'answers' => [['question_id' => $q->id, 'answer' => 'To run more.']],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', JoinRequestStatus::Approved->value);

        // Membership granted, exactly as a normal join grants it.
        $member = $community->members()->where('profile_id', $applicant->id)->first();
        $this->assertNotNull($member);
        $this->assertSame(CommunityMemberStatus::Active, $member->status);
        $this->assertNotNull($member->tier_id, 'the default tier should be assigned');

        // The answers are kept for the record.
        $this->assertSame(1, CommunityJoinAnswer::query()->count());
    }

    /**
     * The guard that makes this safe to deploy: an open community with no
     * questions behaves exactly as it always has, so today's app — which calls
     * /join — is unaffected.
     */
    public function test_an_open_community_without_questions_still_refuses(): void
    {
        $community = $this->community(JoinPolicy::Open);

        $this->actingAs($this->applicant())
            ->postJson("/api/v1/communities/{$community->id}/join-requests")
            ->assertStatus(422)
            ->assertJsonPath('error', 'community_is_open');

        $this->assertSame(0, $community->joinRequests()->count());
    }

    public function test_a_retired_question_is_not_required_but_its_answers_survive(): void
    {
        $community = $this->community(JoinPolicy::InviteOnly);
        $q = $this->ask($community, 'Will be retired', true, 1);
        $applicant = $this->applicant();

        $this->actingAs($applicant)
            ->postJson("/api/v1/communities/{$community->id}/join-requests", [
                'answers' => [['question_id' => $q->id, 'answer' => 'Answered before retirement.']],
            ])
            ->assertStatus(201);

        $q->update(['is_active' => false]);

        // A later applicant is not asked it.
        $this->actingAs($this->applicant())
            ->postJson("/api/v1/communities/{$community->id}/join-requests", ['answers' => []])
            ->assertStatus(201);

        // And the leader can still read the earlier answer, prompt included.
        $response = $this->actingAs($this->leader)
            ->getJson("/api/v1/communities/{$community->id}/join-requests");

        $withAnswers = collect($response->json('data'))
            ->first(fn ($r) => ! empty($r['answers']));
        $this->assertNotNull($withAnswers);
        $this->assertSame('Will be retired', $withAnswers['answers'][0]['prompt']);
    }

    public function test_an_existing_member_cannot_apply_again(): void
    {
        $community = $this->community(JoinPolicy::InviteOnly);
        $this->ask($community, 'Why join?', false, 1);
        $member = $this->applicant();

        $community->members()->create([
            'profile_id' => $member->id,
            'can_manage' => false,
            'status' => CommunityMemberStatus::Active->value,
            'joined_at' => Carbon::now(),
        ]);

        $this->actingAs($member)
            ->postJson("/api/v1/communities/{$community->id}/join-requests", ['answers' => []])
            ->assertStatus(422)
            ->assertJsonPath('error', 'already_member');
    }

    // -------------------------------------------------------------------------
    // Backwards compatibility and prompt history (code review, 2026-08-22)
    // -------------------------------------------------------------------------

    /**
     * The shipped app posts no `answers` key at all
     * (CommunityService::requestToJoin sends an empty body). Holding it to the
     * required-question rules would lock every installed build out of joining
     * the moment a leader adds a required question.
     */
    public function test_a_client_that_sends_no_answers_key_can_still_apply(): void
    {
        $community = $this->community(JoinPolicy::InviteOnly);
        $this->ask($community, 'Required for new clients', true, 1);

        // No `answers` key — exactly what the shipped app sends.
        $this->actingAs($this->applicant())
            ->postJson("/api/v1/communities/{$community->id}/join-requests")
            ->assertStatus(201);
    }

    /**
     * A client that DOES know about answers is held to the rules, even when it
     * sends an empty list.
     */
    public function test_a_client_that_sends_an_empty_answers_key_is_held_to_the_rules(): void
    {
        $community = $this->community(JoinPolicy::InviteOnly);
        $this->ask($community, 'Required', true, 1);

        $this->actingAs($this->applicant())
            ->postJson("/api/v1/communities/{$community->id}/join-requests", ['answers' => []])
            ->assertStatus(422)
            ->assertJsonPath('error', 'missing_required_answers');
    }

    /**
     * Retiring is not enough on its own: a leader can reword a LIVE question,
     * and an application must keep reading as the applicant saw it.
     */
    public function test_rewording_a_question_does_not_rewrite_an_old_application(): void
    {
        $community = $this->community(JoinPolicy::InviteOnly);
        $q = $this->ask($community, 'Original wording', true, 1);

        $this->actingAs($this->applicant())
            ->postJson("/api/v1/communities/{$community->id}/join-requests", [
                'answers' => [['question_id' => $q->id, 'answer' => 'My answer.']],
            ])
            ->assertStatus(201);

        $this->actingAs($this->leader)
            ->patchJson("/api/v1/communities/{$community->id}/join-questions/{$q->id}", [
                'prompt' => 'Completely different wording',
            ])
            ->assertStatus(200);

        $response = $this->actingAs($this->leader)
            ->getJson("/api/v1/communities/{$community->id}/join-requests");

        $this->assertSame(
            'Original wording',
            $response->json('data.0.answers.0.prompt'),
            'the leader must see the question as it was actually asked'
        );
    }

    /**
     * A retired question keeps its position, so a replacement must not land on
     * top of a live one — and the order has to be deterministic either way.
     */
    public function test_a_replacement_question_does_not_collide_with_a_live_one(): void
    {
        $community = $this->community(JoinPolicy::InviteOnly);
        $ids = [];
        for ($i = 1; $i <= 5; $i++) {
            $ids[] = $this->actingAs($this->leader)->postJson(
                "/api/v1/communities/{$community->id}/join-questions",
                ['prompt' => "Q$i"]
            )->json('data.id');
        }

        // Retire the second, then add a replacement.
        $this->actingAs($this->leader)
            ->deleteJson("/api/v1/communities/{$community->id}/join-questions/{$ids[1]}");

        $replacement = $this->actingAs($this->leader)->postJson(
            "/api/v1/communities/{$community->id}/join-questions",
            ['prompt' => 'Replacement']
        );

        $replacement->assertStatus(201);
        // Past the highest live position (5), not back onto the retired 2.
        $this->assertSame(6, $replacement->json('data.position'));

        $set = collect(
            $this->actingAs($this->applicant())
                ->getJson("/api/v1/communities/{$community->id}/join-questions")
                ->json('data.questions')
        )->pluck('prompt')->all();

        $this->assertSame(['Q1', 'Q3', 'Q4', 'Q5', 'Replacement'], $set);
    }

    public function test_answers_are_length_validated(): void
    {
        $community = $this->community(JoinPolicy::InviteOnly);
        $q = $this->ask($community, 'Why join?', true, 1);

        $this->actingAs($this->applicant())
            ->postJson("/api/v1/communities/{$community->id}/join-requests", [
                'answers' => [['question_id' => $q->id, 'answer' => str_repeat('a', 2001)]],
            ])
            ->assertStatus(422);
    }
}

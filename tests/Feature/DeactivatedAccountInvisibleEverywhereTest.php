<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Application;
use App\Models\BusinessProfile;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityProfile;
use App\Models\Event;
use App\Models\Kolab;
use App\Models\Profile;
use App\Services\Admin\ManagedProfileService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * A switched-off account appears nowhere in the app (#258, #260).
 *
 * Product decision, restated by Volkan 2026-08-31: an inactive business,
 * community or attendee must not be visible anywhere, and their kolab must not
 * be in the feed either.
 *
 * This deactivates one of each role — each with real content and real
 * relationships to an ACTIVE viewer — and then sweeps every app-facing read
 * surface for a recognisable marker. It is a sweep rather than a handful of
 * targeted cases on purpose: the leaks found while writing it were in the
 * places nobody would have thought to check (the applicant's own sent list,
 * a community roster, a friends list, and profile detail answering 200 with a
 * blanked-out body instead of 404).
 *
 * Adding an endpoint that exposes other people's content? Add it here.
 */
class DeactivatedAccountInvisibleEverywhereTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_deactivated_account_is_invisible_on_every_read_surface(): void
    {
        $viewer = Profile::factory()->community()->create(['name' => 'Viewer Co']);
        CommunityProfile::factory()->create(['profile_id' => $viewer->id, 'name' => 'Viewer Co']);
        $viewerCommunity = Community::factory()->create([
            'owner_profile_id' => $viewer->id, 'name' => 'Viewer Community', 'join_policy' => 'open',
        ]);

        // --- the three deactivated actors, each with content
        $biz = Profile::factory()->business()->create(['name' => 'ZBIZ', 'email' => 'zbiz@example.com']);
        BusinessProfile::factory()->create(['profile_id' => $biz->id, 'name' => 'ZBIZMARK']);
        $kolab = Kolab::factory()->create(['creator_profile_id' => $biz->id, 'title' => 'ZKOLABMARK']);

        $com = Profile::factory()->community()->create(['name' => 'ZCOM', 'email' => 'zcom@example.com']);
        CommunityProfile::factory()->create(['profile_id' => $com->id, 'name' => 'ZCOMMARK']);
        $community = Community::factory()->create([
            'owner_profile_id' => $com->id, 'name' => 'ZCOMMUNITYMARK', 'join_policy' => 'open',
        ]);
        Event::factory()->create([
            'profile_id' => $com->id, 'community_id' => $community->id, 'name' => 'ZEVENTMARK',
        ]);

        $att = Profile::factory()->attendee()->create(['name' => 'ZATTMARK', 'email' => 'zatt@example.com']);
        \App\Models\AttendeeProfile::factory()->create(['profile_id' => $att->id]);
        CommunityMember::factory()->create([
            'community_id' => $viewerCommunity->id, 'profile_id' => $att->id, 'status' => 'active',
        ]);

        // the viewer applied to the deactivated business's kolab
        Application::factory()->create(['kolab_id' => $kolab->id, 'applicant_profile_id' => $viewer->id]);

        // give the attendee real presence on the viewer's own event, so the
        // signup / checkin / leaderboard surfaces have something to leak
        $viewerEvent = Event::factory()->create([
            'profile_id' => $viewer->id, 'community_id' => $viewerCommunity->id, 'name' => 'Viewer Event',
        ]);
        \App\Models\EventSignup::query()->create([
            'event_id' => $viewerEvent->id, 'profile_id' => $att->id, 'status' => 'going',
        ]);
        \App\Models\EventCheckin::factory()->create([
            'event_id' => $viewerEvent->id, 'profile_id' => $att->id,
        ]);
        \App\Models\PointLedger::factory()->create([
            'profile_id' => $att->id, 'points' => 500,
        ]);
        \App\Models\Friendship::factory()->create([
            'requester_profile_id' => $viewer->id, 'addressee_profile_id' => $att->id, 'status' => 'accepted',
        ]);

        $svc = app(ManagedProfileService::class);
        foreach ([$biz, $com, $att] as $p) {
            $svc->deactivate($p);
        }

        $markers = ['ZBIZMARK', 'ZKOLABMARK', 'ZCOMMARK', 'ZCOMMUNITYMARK', 'ZEVENTMARK', 'ZATTMARK'];

        $endpoints = [
            'kolabs', 'opportunities', 'discovery/opportunities', 'communities/discover',
            'events', 'events/discover', 'multi-kolab-events', 'suggestions',
            'leaderboard/global', 'profiles/lookup?q=Z', 'me/applications',
            'me/received-applications', 'collaborations', 'chats', 'me/communities',
            'communities/'.$viewerCommunity->id.'/members',
            'communities/'.$viewerCommunity->id.'/leaderboard',
            'profiles/'.$biz->id, 'profiles/'.$com->id, 'profiles/'.$att->id,
            'communities/'.$community->id,
            'profiles/'.$biz->id.'/public-profile',
            'events/'.'{VE}'.'/signups',
            'events/'.'{VE}'.'/checkins',
            'events/'.'{VE}'.'/leaderboard',
            'me/friends',
            'me/friend-requests',
            'communities/'.$viewerCommunity->id.'/join-requests',
            'communities/'.$viewerCommunity->id.'/rewards-hub',
        ];
        $endpoints = array_map(fn ($p) => str_replace('{VE}', $viewerEvent->id, $p), $endpoints);

        $report = [];
        foreach ($endpoints as $path) {
            $r = $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/'.$path);
            $body = $r->getContent() ?: '';
            $leaks = array_values(array_filter($markers, fn ($m) => str_contains($body, $m)));
            $report[$path] = $r->status().($leaks === [] ? '  clean' : '  LEAKS: '.implode(',', $leaks));
        }

        $leaking = array_filter($report, fn (string $line): bool => str_contains($line, 'LEAKS'));

        $this->assertSame(
            [],
            $leaking,
            "A deactivated account is still visible on:\n".json_encode(
                $leaking,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );
    }
}

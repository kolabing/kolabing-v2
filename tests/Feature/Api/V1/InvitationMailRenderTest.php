<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Mail\CommunityInvitationMail;
use App\Models\CommunityInvitation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class InvitationMailRenderTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_invitation_mail_renders(): void
    {
        $inv = CommunityInvitation::factory()->create();
        $html = (new CommunityInvitationMail($inv))->render();
        $this->assertStringContainsString($inv->token, $html);
        $this->assertStringContainsString($inv->community->name, $html);
    }
}

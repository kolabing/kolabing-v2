<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\CrmAccount;
use PHPUnit\Framework\TestCase;

class CrmPipelineServiceTest extends TestCase
{
    public function test_current_stage_defaults_to_target(): void
    {
        $this->assertSame('Target', (new CrmAccount)->currentStage());
        $this->assertSame('Contacted', (new CrmAccount(['status' => 'Contacted']))->currentStage());
        // A stray legacy status falls back to the funnel entry.
        $this->assertSame('Target', (new CrmAccount(['status' => 'Lost']))->currentStage());
    }

    public function test_next_stage_walks_the_forward_funnel_and_stops_at_onboarded(): void
    {
        $this->assertSame('Contacted', (new CrmAccount(['status' => 'Target']))->nextStage());
        $this->assertSame('Onboarded', (new CrmAccount(['status' => 'Negotiating']))->nextStage());
        $this->assertNull((new CrmAccount(['status' => 'Onboarded']))->nextStage());
        // Rejected is terminal — not on the forward funnel.
        $this->assertNull((new CrmAccount(['status' => 'Rejected']))->nextStage());
    }
}

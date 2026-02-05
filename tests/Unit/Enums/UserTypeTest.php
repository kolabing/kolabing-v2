<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\UserType;
use PHPUnit\Framework\TestCase;

class UserTypeTest extends TestCase
{
    public function test_attendee_case_exists(): void
    {
        $this->assertSame('attendee', UserType::Attendee->value);
    }

    public function test_values_includes_attendee(): void
    {
        $values = UserType::values();

        $this->assertContains('attendee', $values);
        $this->assertCount(3, $values);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\NotificationPreference;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NotificationPreferenceQuietHoursTest extends TestCase
{
    public function test_quiet_hours_are_detected_for_same_day_window(): void
    {
        $preference = new NotificationPreference([
            'quiet_hours_start' => '22:00:00',
            'quiet_hours_end' => '23:59:59',
            'timezone' => 'Europe/Madrid',
        ]);

        $this->assertTrue($preference->isQuietHoursActive(Carbon::parse('2026-05-09 22:30:00', 'Europe/Madrid')));
        $this->assertFalse($preference->isQuietHoursActive(Carbon::parse('2026-05-09 21:30:00', 'Europe/Madrid')));
    }

    public function test_quiet_hours_support_overnight_windows(): void
    {
        $preference = new NotificationPreference([
            'quiet_hours_start' => '22:00:00',
            'quiet_hours_end' => '07:00:00',
            'timezone' => 'Europe/Madrid',
        ]);

        $this->assertTrue($preference->isQuietHoursActive(Carbon::parse('2026-05-09 23:30:00', 'Europe/Madrid')));
        $this->assertTrue($preference->isQuietHoursActive(Carbon::parse('2026-05-10 06:30:00', 'Europe/Madrid')));
        $this->assertFalse($preference->isQuietHoursActive(Carbon::parse('2026-05-10 12:00:00', 'Europe/Madrid')));
    }
}

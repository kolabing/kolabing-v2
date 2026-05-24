<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\NotificationReminderService;
use Illuminate\Console\Command;

class SendNotificationReminders extends Command
{
    /**
     * @var string
     */
    protected $signature = 'notifications:send-reminders {--limit=100 : Maximum reminders to process per run}';

    /**
     * @var string
     */
    protected $description = 'Send due notification reminders';

    public function __construct(
        private readonly NotificationReminderService $notificationReminderService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->notificationReminderService->sendDueReminders(
            (int) $this->option('limit')
        );

        $this->info("Processed {$count} reminder notification(s).");

        return self::SUCCESS;
    }
}

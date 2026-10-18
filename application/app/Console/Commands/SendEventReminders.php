<?php

namespace App\Console\Commands;

use App\Services\EventReminderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('events:send-reminders')]
#[Description('Ставит в очередь напоминания держателям билетов о мероприятиях, которые начнутся в ближайшие 24 часа')]
class SendEventReminders extends Command
{
    public function handle(EventReminderService $service): int
    {
        $count = $service->sendDueReminders();

        $this->info("Напоминания поставлены в очередь для мероприятий: {$count}");

        return self::SUCCESS;
    }
}

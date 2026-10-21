<?php

namespace App\Console\Commands;

use App\Services\EventStatusService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('events:update-statuses')]
#[Description('Отмечает начавшиеся мероприятия (EventStarted) и переводит прошедшие в finished')]
class UpdateEventStatuses extends Command
{
    public function handle(EventStatusService $service): int
    {
        $started = $service->startDueEvents();
        $finished = $service->finishEndedEvents();

        $this->info("Началось мероприятий: {$started}, завершилось: {$finished}");

        return self::SUCCESS;
    }
}

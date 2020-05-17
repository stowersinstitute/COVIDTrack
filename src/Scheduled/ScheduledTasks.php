<?php


namespace App\Scheduled;


use Zenstruck\ScheduleBundle\Schedule;
use Zenstruck\ScheduleBundle\Schedule\ScheduleBuilder;

class ScheduledTasks implements ScheduleBuilder
{
    public function buildSchedule(Schedule $schedule): void
    {
        $schedule->environments('prod');

        $schedule->addCommand('app:cleanup:excel-import-data', '--force')
            ->description('Clean up unfinished Excel imports')
            ->dailyAt('03:00');
    }

}
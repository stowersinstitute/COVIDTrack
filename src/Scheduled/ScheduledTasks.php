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

        $schedule->addCommand('app:report:notify-on-recommended-viral-result')
            ->description('Notify privileged users like Study Coordinator when a new Viral result indicates a Participant Group recommended for CLIA testing')
            ->everyFiveMinutes();

        $schedule->addCommand('app:report:notify-on-non-negative-viral-result')
            ->description('Notify privileged users like Study Coordinator when a new Non-Negative Viral result is reported')
            ->everyFiveMinutes();

        $schedule->addCommand('app:report:notify-on-antibody-result')
            ->description('Notify privileged users like Study Coordinator when Antibody results have been reported')
            ->everyFiveMinutes();
    }

}
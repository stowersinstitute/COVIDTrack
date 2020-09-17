<?php

namespace App\Scheduled;

use App\Command\CleanupExcelImportData;
use App\Command\Report\NotifyOnAntibodyResultsCommand;
use App\Command\Report\NotifyOnNonNegativeViralResultCommand;
use App\Command\Report\NotifyOnRecommendedCliaViralResultsCommand;
use App\Command\WebHook\ResultCommand;
use App\Command\WebHook\TubesExternalProcessingCommand;
use Zenstruck\ScheduleBundle\Schedule;
use Zenstruck\ScheduleBundle\Schedule\ScheduleBuilder;

class ScheduledTasks implements ScheduleBuilder
{
    public function buildSchedule(Schedule $schedule): void
    {
        $schedule->environments('prod');

        $schedule->addCommand(TubesExternalProcessingCommand::getDefaultName())
            ->description('Web Hook: Publish Tubes Marked External Processing')
            ->everyFiveMinutes();

        $schedule->addCommand(ResultCommand::getDefaultName())
            ->description('Web Hook: Publish Results')
            ->everyFiveMinutes();

        $schedule->addCommand(CleanupExcelImportData::getDefaultName(), '--force')
            ->description('Clean up unfinished Excel imports')
            ->dailyAt('03:00');

        $schedule->addCommand(NotifyOnRecommendedCliaViralResultsCommand::getDefaultName())
            ->description('Notify privileged users like Study Coordinator when a new Viral result indicates a Participant Group recommended for CLIA testing')
            ->everyFiveMinutes();

        $schedule->addCommand(NotifyOnNonNegativeViralResultCommand::getDefaultName())
            ->description('Notify privileged users like Study Coordinator when a new Non-Negative Viral result is reported')
            ->everyFiveMinutes();

        $schedule->addCommand(NotifyOnAntibodyResultsCommand::getDefaultName())
            ->description('Notify privileged users like Study Coordinator when Antibody results have been reported')
            ->everyFiveMinutes();
    }
}

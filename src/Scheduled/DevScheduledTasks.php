<?php


namespace App\Scheduled;


use Zenstruck\ScheduleBundle\Schedule;
use Zenstruck\ScheduleBundle\Schedule\ScheduleBuilder;

class DevScheduledTasks implements ScheduleBuilder
{
    public function buildSchedule(Schedule $schedule): void
    {
        // Add tasks here for testing. Move them to ScheduledTasks.php before merging to master
    }

}
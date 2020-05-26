<?php


namespace App\tests\Scheduling;


use App\Entity\ParticipantGroup;
use App\Entity\DropOffSchedule;
use App\Scheduling\ParticipantGroupRoundRobinScheduler;
use App\Scheduling\ScheduleCalculator;
use PHPUnit\Framework\TestCase;

class ParticipantGroupRoundRobinSchedulerTest extends TestCase
{
    /**
     * 9 groups that drop off on Tuesday and Thursday between 9am and 11am
     *
     * Groups are tested twice a week so are scheduled to drop off on both days
     */
    public function testTwiceWeekly()
    {
        $schedule = new DropOffSchedule('Tu Th 8am-5pm');

        $schedule->setDaysOfTheWeek([
            DropOffSchedule::TUESDAY,
            DropOffSchedule::THURSDAY,
        ]);
        $schedule->setDailyStartTime(new \DateTimeImmutable('09:00:00'));
        $schedule->setDailyEndTime(new \DateTimeImmutable('11:00:00'));
        $schedule->setWindowIntervalMinutes(30);
        $schedule->setNumExpectedDropOffsPerGroup(2);

        // Calculate windows in the schedule
        $calculator = new ScheduleCalculator($schedule);
        $calculator->getWeeklyWindows(); // This adds DropOffWindows to the DropOffSchedule

        /** @var ParticipantGroup[] $groups */
        $groups = [
            new ParticipantGroup('G-100', 1),
            new ParticipantGroup('G-200', 1),
            new ParticipantGroup('G-300', 1),
            new ParticipantGroup('G-400', 1),
            new ParticipantGroup('G-500', 1),
            new ParticipantGroup('G-600', 1),
            new ParticipantGroup('G-700', 1),
            new ParticipantGroup('G-800', 1),
            new ParticipantGroup('G-900', 1),
        ];

        $scheduler = new ParticipantGroupRoundRobinScheduler();
        $scheduler->assignByDays($groups, $schedule);

        foreach ($groups as $group) {
            $this->assertCount($schedule->getNumExpectedDropOffsPerGroup(), $group->getDropOffWindows(), 'Group had an unexpected number of drop-off windows');
        }
    }
}
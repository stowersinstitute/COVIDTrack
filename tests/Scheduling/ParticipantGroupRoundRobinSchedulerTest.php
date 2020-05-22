<?php


namespace App\tests\Scheduling;


use App\Entity\ParticipantGroup;
use App\Entity\SiteDropOffSchedule;
use App\Scheduling\ParticipantGroupRoundRobinScheduler;
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
        $schedule = new SiteDropOffSchedule('Tu Th 8am-5pm');

        $schedule->setDaysOfTheWeek([
            SiteDropOffSchedule::TUESDAY,
            SiteDropOffSchedule::THURSDAY,
        ]);
        $schedule->setDailyStartTime(new \DateTimeImmutable('09:00:00'));
        $schedule->setDailyEndTime(new \DateTimeImmutable('11:00:00'));
        $schedule->setWindowIntervalMinutes(30);
        $schedule->setNumExpectedDropOffsPerGroup(2);

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

        print "\n";
        foreach ($groups as $group) {
            print $group->getAccessionId() . ': ' . $group->getDropOffWindowDebugString() . "\n";
        }
    }
}
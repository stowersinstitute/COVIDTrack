<?php


namespace App\Tests\Entity;


use App\Entity\DropOffSchedule;
use PHPUnit\Framework\TestCase;

class DropOffScheduleTest extends TestCase
{
    /**
     * Tuesdays and Thursdays, 8am - 5pm
     */
    public function testTuesThursWorkdayRrule()
    {
        $schedule = new DropOffSchedule('Tuesdays and Thursdays 8am - 5pm');

        $schedule->setDaysOfTheWeek([
            DropOffSchedule::TUESDAY,
            DropOffSchedule::THURSDAY,
        ]);
        $schedule->setDailyStartTime(new \DateTimeImmutable('09:00:00'));
        $schedule->setDailyEndTime(new \DateTimeImmutable('17:00:00'));
        $schedule->setWindowIntervalMinutes(30);

        $expected = 'FREQ=DAILY;INTERVAL=1;WKST=MO;BYDAY=TU,TH;BYHOUR=09,10,11,12,13,14,15,16;BYMINUTE=0,30';
        $this->assertEquals($expected, $schedule->getRruleString());
    }
}
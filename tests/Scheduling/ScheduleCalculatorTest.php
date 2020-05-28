<?php


namespace App\Tests\Scheduling;


use App\Entity\DropOffSchedule;
use App\Scheduling\ScheduleCalculator;
use App\Util\DateUtils;
use PHPUnit\Framework\TestCase;

class ScheduleCalculatorTest extends TestCase
{
    public function testGenerateWeeklyWindows()
    {
        $schedule = new DropOffSchedule('Test');

        $schedule->setDaysOfTheWeek([
            DropOffSchedule::TUESDAY,
            DropOffSchedule::THURSDAY,
        ]);
        $schedule->setDailyStartTime(new \DateTimeImmutable('09:00:00'));
        $schedule->setDailyEndTime(new \DateTimeImmutable('17:00:00'));
        $schedule->setWindowIntervalMinutes(30);

        $calculator = new ScheduleCalculator($schedule);

        $windows = $calculator->getWeeklyWindows();

        $this->assertCount(32, $windows, 'Unexpected number of windows generated');

        foreach ($windows as $window) {
            // Represents the day that $window is on
            $referenceDate = \DateTime::createFromFormat(DATE_ATOM, $window->getStartsAt()->format(DATE_ATOM));
            $referenceDate->setTime(0, 0, 0);

            $openTime = DateUtils::copyTimeOfDay(new \DateTimeImmutable('09:00:00'), $referenceDate);
            $closeTime = DateUtils::copyTimeOfDay(new \DateTimeImmutable('17:00:00'), $referenceDate);

            $windowStartTime = DateUtils::copyTimeOfDay($window->getStartsAt(), $referenceDate);
            $windowEndTime = DateUtils::copyTimeOfDay($window->getEndsAt(), $referenceDate);

            // Window cannot start before opening (8am)
            $this->assertGreaterThanOrEqual($openTime, $windowStartTime, 'Window started before facility was open');

            // Window cannot start after closing
            $this->assertLessThan($closeTime, $windowStartTime, 'Window started after facility was closed');

            // Window cannot end after closing
            $this->assertLessThanOrEqual($closeTime, $windowEndTime, 'Window ended after facility was closed');
        }
    }
}
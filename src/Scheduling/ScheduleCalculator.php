<?php


namespace App\Scheduling;


use App\Entity\DropOffWindow;
use App\Entity\SiteDropOffSchedule;
use App\Util\DateUtils;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;

class ScheduleCalculator
{
    /** @var SiteDropOffSchedule */
    protected $schedule;

    public function __construct(SiteDropOffSchedule $schedule)
    {
        $this->schedule = $schedule;
    }

    /**
     * @return DropOffWindow[]
     */
    public function getWeeklyWindows() : array
    {
        $schedule = $this->schedule;
        $windows = [];

        // We need a period of time to use as a reference. The exact period doesn't really matter,
        // but it cannot be "now" because that may generate a partial schedule
        $calculationStartDate = new \DateTimeImmutable('2020-05-10 00:00:00');  // Sunday
        $calculationEndDate = new \DateTimeImmutable('2020-05-17 00:00:00');    // Following Monday at midnight

        // Generate an RRULE that will provide times during a week
        $rule = (new Rule($schedule->getRruleString()))
            // Note that these dates don't matter, we just need any full week since we discard the day
            ->setStartDate(DateUtils::copyTimeOfDay($schedule->getDailyStartTime(), $calculationStartDate))
            ->setEndDate(DateUtils::copyTimeOfDay($schedule->getDailyEndTime(), $calculationEndDate))
        ;

        $transformer = new ArrayTransformer();
        $rawTimes = $transformer->transform($rule);

        foreach ($rawTimes as $rawTime) {
            $windowStartsAt = DateUtils::toImmutable($rawTime->getStart());
            $windowEndsAt = DateUtils::toImmutable($rawTime->getStart(), sprintf('+%s minutes', $schedule->getWindowIntervalMinutes()));

            // todo: Bug? transformer generates dates through 2020-10-15 without this check
            if ($windowStartsAt > $calculationEndDate) break;

            $windows[] = new DropOffWindow($schedule, $windowStartsAt, $windowEndsAt);
        }

        return $windows;
    }

    /**
     * Returns an array where the keys are days of the week (eg. 'Mon', 'Tue') and the value
     * is an array of all windows occurring on that day
     *
     * @return array
     */
    public function getWindowsByWeekday() : array
    {
        /*
         * Keys are the weekday and values are windows within that day
         */
        $byWeekday = [];

        foreach ($this->getWeeklyWindows() as $window) {
            $weekday = $this->toRruleWeekday($window->getStartsAt()->format('D'));

            if (!isset($byWeekday[$weekday])) {
                $byWeekday[$weekday] = [];
            }

            $byWeekday[$weekday][] = $window;
        }

        return $byWeekday;
    }

    /**
     * Converts a three-letter weekday (eg. from the 'D' date format) to same format the drop off schedule uses
     *
     * Example:
     *      Mon -> MO
     */
    protected function toRruleWeekday(string $threeLetterWeekday) : string
    {
        return strtoupper(substr($threeLetterWeekday, 0, 2));
    }
}
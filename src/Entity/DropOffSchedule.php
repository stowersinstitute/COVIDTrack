<?php


namespace App\Entity;

use App\Util\DateUtils;
use App\Util\EntityUtils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\ArrayTransformerConfig;

/**
 * Times that the drop off facilities are open
 *
 * @ORM\Entity(repositoryClass="App\Entity\DropOffScheduleRepository")
 * @ORM\Table(name="drop_off_schedules")
 */
class DropOffSchedule
{
    const VALID_DAYS_OF_THE_WEEK = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];

    const MONDAY    = 'MO';
    const TUESDAY   = 'TU';
    const WEDNESDAY = 'WE';
    const THURSDAY  = 'TH';
    const FRIDAY    = 'FR';
    const SATURDAY  = 'SA';
    const SUNDAY    = 'SU';

    /**
     * @var integer|null
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     *
     */
    protected $id;

    /**
     * @var string Brief description of this schedule, eg. "Viral Testing"
     *
     * @ORM\Column(name="label", type="string", length=255, nullable=false)
     */
    protected $label;

    /**
     * @var string|null Human-readable details about the schedule, eg. Tuesdays and Thursdays, 8am - 5pm
     *
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    protected $description;

    /**
     * @var string|null Human-readable description of where specimens can be physically dropped off, eg. "Kiosks in buildings 3 and 5"
     *
     * @ORM\Column(name="location", type="text", nullable=true)
     */
    protected $location;

    /**
     * @var string[]|null Days of the week when this schedule is active
     * RRULE_PROPERTY
     *
     * Valid values are in the VALID_DAYS_OF_THE_WEEK constant
     *
     * @ORM\Column(name="days_of_the_week", type="simple_array", nullable=true)
     */
    protected $daysOfTheWeek;

    /**
     * @var \DateTimeImmutable|null Time of day when the drop off facility opens
     * RRULE_PROPERTY
     *
     * @ORM\Column(name="daily_start_time", type="time_immutable", nullable=true)
     */
    protected $dailyStartTime;

    /**
     * @var \DateTimeImmutable|null Time of day when the drop off facility closes
     * RRULE_PROPERTY
     *
     * @ORM\Column(name="daily_end_time", type="time_immutable", nullable=true)
     */
    protected $dailyEndTime;

    /**
     * @var int|null Length of each window in minutes. Eg. 30 minute windows would be 9:00, 9:30, 10:00, 10:30, etc.
     * RRULE_PROPERTY
     *
     * @ORM\Column(name="window_interval_minutes", type="integer", nullable=true)
     */
    protected $windowIntervalMinutes;

    /**
     * @var int|null How many times each group is expected to drop off specimens in this schedule
     *
     * @ORM\Column(name="num_expected_drop_offs_per_group", type="integer", nullable=true)
     */
    protected $numExpectedDropOffsPerGroup;

    /**
     * @var DropOffWindow[] Drop off windows associated with this schedule
     *
     * @ORM\OneToMany(targetEntity="DropOffWindow", mappedBy="schedule", cascade={"persist", "remove"}, orphanRemoval=true, fetch="EAGER")
     * @ORM\OrderBy({"startsAt" = "ASC"})
     */
    protected $dropOffWindows;

    public function __construct(string $label)
    {
        $this->label = $label;
        $this->windowIntervalMinutes = 30;

        $this->daysOfTheWeek = [];
        $this->dropOffWindows = new ArrayCollection();
    }

    /**
     * Updates $rrule to match the configuration in other properties
     *
     * See:
     *  - properties tagged with RRULE_PROPERTY
     *  - https://jakubroztocil.github.io/rrule/
     *
     * Example RRULEs:
     *
     *  Tuesday and Thursday, 8am - 5pm
     *      FREQ=DAILY;INTERVAL=1;WKST=MO;BYDAY=TU,TH;BYHOUR=8,9,10,11,12,13,14,15,16,17;BYMINUTE=0,30
     */
    public function getRruleString()
    {
        // Standard properties common to all rrules
        $rruleParts = ['FREQ=DAILY', 'INTERVAL=1', 'WKST=MO'];

        // BYDAY
        if ($this->daysOfTheWeek) {
            $rruleParts[] = 'BYDAY=' . join(',', $this->daysOfTheWeek);
        }

        // BYHOUR is calculated by the start and end time
        $byHours = [];
        $startHour = $this->dailyStartTime->format('H');
        $endHour = $this->dailyEndTime->format('H');

        $currHour = $startHour;
        while ($currHour < $endHour) {
            $byHours[] = $currHour;
            $currHour++;
        }

        $rruleParts[] = 'BYHOUR=' . join(',', $byHours);

        // BYMINUTE depends on the window interval. 0 is always included if there is an interval
        if ($this->windowIntervalMinutes) {
            $byMinutes = [];
            $currMinute = 0;
            while ($currMinute < 60) {
                $byMinutes[] = $currMinute;

                $currMinute += $this->windowIntervalMinutes;
            }

            $rruleParts[] = 'BYMINUTE=' . join(',', $byMinutes);
        }

        return join(';', $rruleParts);
    }

    /**
     * Returns a \DateTimeImmutable representing when the first drop off window on the next
     * drop off day starts. This is relative to the current time.
     */
    public function getNextDropOffWindowStartsAt() : ?\DateTimeImmutable
    {
        $rule = new Rule($this->getRruleString(), new \DateTimeImmutable(), new \DateTimeImmutable('+1 week'));

        $config = new ArrayTransformerConfig();
        $config->setVirtualLimit(1);
        $transformer = new ArrayTransformer($config);
        $upcoming = $transformer->transform($rule);

        return $upcoming ? DateUtils::toImmutable($upcoming[0]->getStart()) : null;
    }

    /**
     * Returns an array of dates representing the week days this schedule is enabled on
     *
     * @return \DateTimeImmutable[]
     */
    public function getDropOffDaysAsDates() : array
    {
        $dayDates = [];
        foreach ($this->dropOffWindows as $window) {
            $weekdayStr = $window->getStartsAt()->format('l');

            if (array_key_exists($weekdayStr, $dayDates)) continue;

            $dayDates[$weekdayStr] = $window->getStartsAt();
        }

        return $dayDates;
    }

    /**
     * @return DropOffWindow[]
     */
    public function getDailyWindowsAsDates() : array
    {
        // A date representing one of the weekdays in the schedule
        // Only used to filter windows to a single day
        $days = $this->getDropOffDaysAsDates();
        $day = array_shift($days);
        $filterDayStr = $day->format('l');

        $dayWindows = [];
        foreach ($this->dropOffWindows as $window) {
            $windowDayStr = $window->getStartsAt()->format('l');
            if ($windowDayStr !== $filterDayStr) continue;

            $dayWindows[] = $window;
        }

        return $dayWindows;
    }

    public function getWindowByWeekDayAndStartTime(\DateTimeInterface $dayDate, \DateTimeInterface $windowStartDate) : ?DropOffWindow
    {
        foreach ($this->dropOffWindows as $window) {
            if ($window->getStartsAt()->format('l') != $dayDate->format('l')) continue;
            if ($window->getStartsAt()->format('His') != $windowStartDate->format('His')) continue;

            // All filters passed, this will only happen for one window
            return $window;
        }

        return null;
    }

    /**
     * Returns an array where the keys are days of the week (eg. 'Mon', 'Tue') and the value
     * is an array of all windows occurring on that day
     *
     * @return array
     */
    public function getWindowsByWeekday() : array
    {
        $byWeekday = [];

        foreach ($this->dropOffWindows as $window) {
            $weekday = $window->getStartsAt()->format('D');

            if (!isset($byWeekday[$weekday])) {
                $byWeekday[$weekday] = [];
            }

            $byWeekday[$weekday][] = $window;
        }

        return $byWeekday;
    }

    /**
     * Returns an array with the following keys:
     *  numParticpants - total number of participants across all groups
     *  numGroups - total number of groups
     *
     * @param string $filterDay a day represented by PHP's 'D' format
     */
    public function getParticipantTotalsOn(string $filterDay) : array
    {
        $filterDay = self::normalizeWeekday($filterDay);
        self::mustBeValidWeekday($filterDay);

        $totals = [
            'numParticipants' => 0,
            'numGroups' => 0,
        ];

        foreach ($this->dropOffWindows as $window) {
            $windowDay = self::normalizeWeekday($window->getStartsAt()->format('D'));
            if ($windowDay !== $filterDay) continue;

            foreach ($window->getParticipantGroups() as $group) {
                $totals['numParticipants'] += $group->getParticipantCount();
            }

            $totals['numGroups'] += count($window->getParticipantGroups());
        }

        return $totals;
    }

    /**
     * @return DropOffWindow[]
     */
    public function getDropOffWindows() : array
    {
        return $this->dropOffWindows->getValues();
    }

    public function addDropOffWindow(DropOffWindow $window)
    {
        if ($this->hasDropOffWindow($window)) return;

        $this->dropOffWindows->add($window);
    }

    public function hasDropOffWindow(DropOffWindow $window) : bool
    {
        foreach ($this->dropOffWindows as $currWindow) {
            if (EntityUtils::isSameEntity($currWindow, $window)) return true;

            // Also need to check if the drop off window represents exactly the same
            // time slot as an existing window
            if ($currWindow->getTimeSlotId() === $window->getTimeSlotId()) return true;
        }

        return false;
    }

    /**
     * Returns all windows that are currently stored in the database
     * @return DropOffWindow[]
     */
    public function getCommittedDropOffWindows() : array
    {
        $all = $this->getDropOffWindows();
        $committed = [];

        foreach ($all as $window) {
            if ($window->getId()) $committed[] = $window;
        }

        return $committed;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): void
    {
        $this->location = $location;
    }

    public function getDailyStartTime(): ?\DateTimeImmutable
    {
        return $this->dailyStartTime;
    }

    public function setDailyStartTime(?\DateTimeImmutable $dailyStartTime): void
    {
        $this->dailyStartTime = $dailyStartTime;
    }

    public function getDailyEndTime(): ?\DateTimeImmutable
    {
        return $this->dailyEndTime;
    }

    public function setDailyEndTime(?\DateTimeImmutable $dailyEndTime): void
    {
        $this->dailyEndTime = $dailyEndTime;
    }

    public function getWindowIntervalMinutes(): ?int
    {
        return $this->windowIntervalMinutes;
    }

    public function setWindowIntervalMinutes(?int $windowIntervalMinutes): void
    {
        $this->windowIntervalMinutes = $windowIntervalMinutes;
    }

    public function getNumExpectedDropOffsPerGroup(): ?int
    {
        return $this->numExpectedDropOffsPerGroup;
    }

    public function setNumExpectedDropOffsPerGroup(?int $numExpectedDropOffsPerGroup): void
    {
        $this->numExpectedDropOffsPerGroup = $numExpectedDropOffsPerGroup;
    }

    /**
     * @return string[]|null
     */
    public function getDaysOfTheWeek(): ?array
    {
        return $this->daysOfTheWeek;
    }

    /**
     * @param string[]|null $daysOfTheWeek
     */
    public function setDaysOfTheWeek(?array $daysOfTheWeek): void
    {
        if ($daysOfTheWeek === null) {
            $this->daysOfTheWeek = null;
            return;
        }

        foreach ($daysOfTheWeek as $day) {
            self::mustBeValidWeekday($day);
        }
        $this->daysOfTheWeek = $daysOfTheWeek;
    }

    public static function isValidDayOfTheWeek($day) : bool
    {
        return in_array($day, static::VALID_DAYS_OF_THE_WEEK);
    }

    public static function mustBeValidWeekday($day)
    {
        if (!self::isValidDayOfTheWeek($day)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid day of the week: "%s". Valid days are: %s',
                $day,
                join(', ', static::VALID_DAYS_OF_THE_WEEK)
            ));
        }
    }

    /**
     * Converts PHP date strings supported by date() to the internal representation of a weekday
     */
    public static function normalizeWeekday($day)
    {
        if (in_array($day, ['Mon', 'Monday']))      return self::MONDAY;
        if (in_array($day, ['Tue', 'Tuesday']))     return self::TUESDAY;
        if (in_array($day, ['Wed', 'Wednesday']))   return self::WEDNESDAY;
        if (in_array($day, ['Thu', 'Thursday']))    return self::THURSDAY;
        if (in_array($day, ['Fri', 'Friday']))      return self::FRIDAY;
        if (in_array($day, ['Sat', 'Saturday']))    return self::SATURDAY;
        if (in_array($day, ['Sun', 'Sunday']))      return self::SUNDAY;

        throw new \InvalidArgumentException('Invalid weekday: ' . $day);
    }
}
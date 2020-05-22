<?php


namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Times that the drop off facilities are open
 *
 * @ORM\Entity(repositoryClass="App\Entity\ParticipantGroupRepository")
 * @ORM\Table(name="site_drop_off_schedules")
 */
class SiteDropOffSchedule
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
     * @ORM\OneToMany(targetEntity="DropOffWindow", mappedBy="schedule", cascade={"persist", "remove"}, orphanRemoval=true)
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
     * @return DropOffWindow[]
     */
    public function getDropOffWindows() : array
    {
        return $this->dropOffWindows->getValues();
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
            if (!in_array($day, static::VALID_DAYS_OF_THE_WEEK)) {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid day of the week: "%s". Valid days are: %s',
                    $day,
                    join(', ', static::VALID_DAYS_OF_THE_WEEK)
                ));
            }
        }
        $this->daysOfTheWeek = $daysOfTheWeek;
    }
}
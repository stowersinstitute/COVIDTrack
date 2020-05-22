<?php


namespace App\Entity;


use App\Entity\SiteDropOffSchedule;
use App\Util\EntityUtils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Windows available within a SiteDropOffSchedule
 *
 * @ORM\Entity(repositoryClass="App\Entity\DropOffWindowRepository")
 * @ORM\Table(name="drop_off_windows")
 */
class DropOffWindow
{
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
     * Schedule that generated this DropOffWindow
     * @var SiteDropOffSchedule
     *
     * @ORM\ManyToOne(targetEntity="SiteDropOffSchedule", inversedBy="dropOffWindows")
     * @ORM\JoinColumn(name="schedule_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected $schedule;

    /**
     * @var \DateTimeImmutable|null Time of day when the window opens
     *
     * @ORM\Column(name="starts_at", type="datetime_immutable", nullable=false)
     */
    protected $startsAt;

    /**
     * @var \DateTimeImmutable|null Time of day when the window ends
     *
     * @ORM\Column(name="ends_at", type="datetime_immutable", nullable=false)
     */
    protected $endsAt;

    /**
     * @var ParticipantGroup[]
     *
     * @ORM\ManyToMany(targetEntity="ParticipantGroup", inversedBy="dropOffWindows")
     * @ORM\JoinTable(
     *     name="drop_off_window_groups",
     *     joinColumns={
     *         @ORM\JoinColumn(name="drop_off_window_id", referencedColumnName="id", onDelete="CASCADE", nullable=false)
     *     },
     *     inverseJoinColumns={
     *         @ORM\JoinColumn(name="participant_group_id", referencedColumnName="id", onDelete="CASCADE", nullable=false)
     *     }
     * )
     */
    protected $particpantGroups;

    public function __construct(SiteDropOffSchedule $schedule, \DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt)
    {
        $this->startsAt = $startsAt;
        $this->endsAt = $endsAt;

        $this->schedule = $schedule;

        $this->particpantGroups = new ArrayCollection();

        $schedule->addDropOffWindow($this);
    }

    /**
     * Returns a string that uniquely identifies this window within its parent schedule
     *
     * Note that this is not dependent on entities being persisted
     */
    public function getTimeSlotId() : string
    {
        return sprintf(
            '%s.%s.%s',
            $this->startsAt->format('D'),
            $this->startsAt->format('His'),
            $this->endsAt->format('His')
        );
    }

    public function getDebugString() : string
    {
        return sprintf(
            '%s %s - %s',
            $this->startsAt->format('D'),
            $this->startsAt->format('h:i:sa'),
            $this->endsAt->format('h:i:sa')
        );
    }

    /**
     * @return ParticipantGroup[]
     */
    public function getParticipantGroups() : array
    {
        return $this->particpantGroups->getValues();
    }

    public function addParticipantGroup(ParticipantGroup $group)
    {
        if ($this->hasParticipantGroup($group)) return;

        $this->particpantGroups->add($group);
        $group->addDropOffWindow($this);
    }

    public function hasParticipantGroup(ParticipantGroup $group)
    {
        foreach ($this->particpantGroups as $hasGroup) {
            if (EntityUtils::isSameEntity($hasGroup, $group)) return true;
        }

        return false;
    }

    public function getNumParticipantGroups() : int
    {
        return $this->particpantGroups->count();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSchedule(): \App\Entity\SiteDropOffSchedule
    {
        return $this->schedule;
    }

    public function setSchedule(\App\Entity\SiteDropOffSchedule $schedule): void
    {
        $this->schedule = $schedule;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(?\DateTimeImmutable $startsAt): void
    {
        $this->startsAt = $startsAt;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(?\DateTimeImmutable $endsAt): void
    {
        $this->endsAt = $endsAt;
    }
}
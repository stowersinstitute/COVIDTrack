<?php


namespace App\Entity;


use App\Entity\DropOffSchedule;
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
     * @var DropOffSchedule
     *
     * @ORM\ManyToOne(targetEntity="DropOffSchedule", inversedBy="dropOffWindows")
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
    protected $participantGroups;

    public function __construct(DropOffSchedule $schedule, \DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt)
    {
        $this->startsAt = $startsAt;
        $this->endsAt = $endsAt;

        $this->schedule = $schedule;

        $this->participantGroups = new ArrayCollection();

        $schedule->addDropOffWindow($this);
    }

    public function getDisplayString() : string
    {
        return sprintf(
            '%s %s-%s',
            $this->startsAt->format('D'),
            $this->startsAt->format('H:i'),
            $this->endsAt->format('H:i')
        );
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
        return $this->participantGroups->getValues();
    }

    public function addParticipantGroup(ParticipantGroup $group)
    {
        if ($this->hasParticipantGroup($group)) return;

        $this->participantGroups->add($group);
        $group->addDropOffWindow($this);
    }

    public function removeParticipantGroup(ParticipantGroup $group)
    {
        if (!$this->hasParticipantGroup($group)) return;

        foreach ($this->participantGroups as $currGroup) {
            if (EntityUtils::isSameEntity($currGroup, $group)) {
                $this->participantGroups->removeElement($currGroup);
                $currGroup->removeDropOffWindow($this);
            }
        }
    }

    public function hasParticipantGroup(ParticipantGroup $group)
    {
        foreach ($this->participantGroups as $hasGroup) {
            if (EntityUtils::isSameEntity($hasGroup, $group)) return true;
        }

        return false;
    }

    public function getNumParticipantGroups() : int
    {
        return $this->participantGroups->count();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSchedule(): \App\Entity\DropOffSchedule
    {
        return $this->schedule;
    }

    public function setSchedule(\App\Entity\DropOffSchedule $schedule): void
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
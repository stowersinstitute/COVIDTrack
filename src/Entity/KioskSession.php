<?php

namespace App\Entity;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Traits\SoftDeleteableEntity;
use App\Traits\TimestampableEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Interaction user has with a Kiosk.
 *
 * @ORM\Entity
 * @ORM\Table(name="kiosk_sessions")
 */
class KioskSession
{
    use TimestampableEntity, SoftDeleteableEntity;

    const SCREEN_GROUP_ENTRY = "GROUP_ENTRY";
    const SCREEN_ADD_TUBES = "ADD_TUBES";
    const SCREEN_REVIEW_TUBES = "REVIEW_TUBES";
    const SCREEN_COMPLETED = "COMPLETED";
    const SCREEN_CANCELED = "CANCELED";

    /**
     * @var integer
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * Kiosk where this session took place
     *
     * @var Kiosk
     * @ORM\ManyToOne(targetEntity="App\Entity\Kiosk")
     * @ORM\JoinColumn(name="kiosk_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $kiosk;

    /**
     * DropOff generated if this Session was finalized.
     *
     * @var null|DropOff
     * @ORM\ManyToOne(targetEntity="App\Entity\DropOff", cascade={"persist"})
     * @ORM\JoinColumn(name="drop_off_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $dropOff;

    /**
     * Screen user most recently completed.
     *
     * @var string
     * @ORM\Column(name="most_recent_screen", type="string")
     */
    private $mostRecentScreen;

    /**
     * Participant Group this user is a member of by scanning their badge.
     *
     * @var null|ParticipantGroup
     * @ORM\ManyToOne(targetEntity="App\Entity\ParticipantGroup")
     * @ORM\JoinColumn(name="participant_group_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $participantGroup;

    /**
     * Holds Tube data user has entered on the Add Tube screen.
     *
     * @var ArrayCollection|KioskSessionTube[]
     * @ORM\OneToMany(targetEntity="App\Entity\KioskSessionTube", mappedBy="kioskSession", cascade={"persist"}, orphanRemoval=true)
     */
    private $tubeData;

    /**
     * @var null|\DateTimeImmutable
     * @ORM\Column(name="canceled_at", type="datetime_immutable", nullable=true)
     */
    private $canceledAt;

    /**
     * @var null|\DateTimeImmutable
     * @ORM\Column(name="completed_at", type="datetime_immutable", nullable=true)
     */
    private $completedAt;

    public function __construct(Kiosk $kiosk)
    {
        $this->tubeData = new ArrayCollection();

        $this->setCreatedAt(new \DateTimeImmutable());
        $this->kiosk = $kiosk;
        $this->mostRecentScreen = self::SCREEN_GROUP_ENTRY;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKiosk(): Kiosk
    {
        return $this->kiosk;
    }

    /**
     * @return string SCREEN_* constants
     */
    public function getMostRecentScreen(): string
    {
        return $this->mostRecentScreen;
    }

    /**
     * @param string $mostRecentScreen SCREEN_* constants
     */
    public function setMostRecentScreen(string $mostRecentScreen): void
    {
        $this->mostRecentScreen = $mostRecentScreen;
    }

    public function getParticipantGroup(): ?ParticipantGroup
    {
        return $this->participantGroup;
    }

    /**
     * When user completes the kiosk screen allowing Participation Group entry.
     */
    public function setParticipantGroup(?ParticipantGroup $group): void
    {
        $this->participantGroup = $group;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->getCreatedAt();
    }

    public function getCanceledAt(): ?\DateTimeImmutable
    {
        return $this->canceledAt;
    }

    public function setCanceledAt(?\DateTimeImmutable $canceledAt): void
    {
        $this->canceledAt = $canceledAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): void
    {
        $this->completedAt = $completedAt;
    }


    public function addTubeData(KioskSessionTube $sessionTube)
    {
        /** @var KioskSessionTube[] $foundSessionTubes */
        $foundSessionTubes = $this->tubeData->filter(function (KioskSessionTube $existingTube) use ($sessionTube) {
            return $existingTube->getTube()->getAccessionId() === $sessionTube->getTube()->getAccessionId();
        });

        // If this session tube is already on this session, delete it first then add the new one.
        if (!$foundSessionTubes->isEmpty()) {
            foreach ($foundSessionTubes as $foundSessionTube) {
                $this->tubeData->removeElement($foundSessionTube);
            }
        }

        $this->tubeData->add($sessionTube);
    }

    /**
     * @return KioskSessionTube[]
     */
    public function getTubeData(): array
    {
        return $this->tubeData->getValues();
    }

    /**
     * When user clicks Finish to complete their kiosk interaction.
     */
    public function finish(SpecimenAccessionIdGenerator $gen): void
    {
        if ($this->mostRecentScreen === self::SCREEN_COMPLETED) {
            // Already finished
            return;
        }
        if ($this->mostRecentScreen === self::SCREEN_CANCELED) {
            throw new \RuntimeException('Cannot finish Kiosk Session that was previously canceled');
        }
        if (!$this->getParticipantGroup()) {
            throw new \RuntimeException('Cannot finish Kiosk Session without selecting Participant Group');
        }
        if (count($this->getTubeData()) < 1) {
            throw new \RuntimeException('Cannot finish Kiosk Session without entering at least one Tube');
        }

        // DropOff groups all Tubes added during this session
        $drop = new DropOff();
        $drop->setKioskSession($this);
        $this->dropOff = $drop;

        $drop->setGroup($this->getParticipantGroup());

        // Add Tubes, Create Specimens
        foreach ($this->tubeData as $sessionTube) {
            $tube = $sessionTube->getTube();
            $tube->kioskDropoffComplete($gen, $drop, $this->getParticipantGroup(), $sessionTube->getTubeType(), $sessionTube->getCollectedAt());
        }

        $this->setMostRecentScreen(self::SCREEN_COMPLETED);
        $this->setCompletedAt(new \DateTimeImmutable());
    }

    /**
     * When user clicks Cancel to abort their kiosk interaction.
     */
    public function cancel()
    {
        if ($this->mostRecentScreen === self::SCREEN_CANCELED) {
            // Already canceled
            return;
        }

        $this->setMostRecentScreen(self::SCREEN_CANCELED);
        $this->setCanceledAt(new \DateTimeImmutable());
    }

    public function isCancelled(): bool
    {
        return $this->mostRecentScreen === self::SCREEN_CANCELED;
    }

    public function getDropOff(): ?DropOff
    {
        return $this->dropOff;
    }
}

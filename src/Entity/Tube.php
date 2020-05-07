<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;

/**
 * Physical container that holds a Specimen.
 *
 * @ORM\Entity(repositoryClass="App\Entity\SpecimenRepository")
 * @ORM\Table(name="tubes")
 */
class Tube
{
    use SoftDeleteableEntity;

    const STATUS_PRINTED = "PRINTED";
    const STATUS_RETURNED = "RETURNED";
    const STATUS_CHECKEDIN = "CHECKEDIN";
    const STATUS_REJECTED = "REJECTED";

    const TYPE_BLOOD = "BLOOD";
    const TYPE_SALIVA = "SALIVA";
    const TYPE_SWAB = "SWAB";

    /**
     * @var int
     * @ORM\Id()
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * Unique public ID for referencing. This is referred to as the "Tube ID"
     *
     * @var string
     * @ORM\Column(name="accessionId", type="string", unique=true)
     */
    private $accessionId;

    /**
     * Participant Group scanned when Tube was returned.
     *
     * @var ParticipantGroup
     * @ORM\ManyToOne(targetEntity="App\Entity\ParticipantGroup")
     * @ORM\JoinColumn(name="participantGroupId", referencedColumnName="id", onDelete="SET NULL")
     */
    private $participantGroup;

    /**
     * Specimen created as result of Tube being checked in.
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Specimen")
     * @ORM\JoinColumn(name="specimenId", referencedColumnName="id", onDelete="SET NULL")
     */
    private $specimen;

    /**
     * Current status of tube.
     *
     * @var string
     * @ORM\Column(name="status", type="string")
     */
    private $status;

    /**
     * @var string
     * @ORM\Column(name="tubeType", type="string", nullable=true)
     */
    private $tubeType;

    /**
     * Date/Time when Tube was returned by the Participant.
     *
     * @var \DateTimeImmutable
     * @ORM\Column(name="returnedAt", type="datetime_immutable", nullable=true)
     */
    private $returnedAt;

    /**
     * @var DropOff
     * @ORM\ManyToOne(targetEntity="App\Entity\DropOff", inversedBy="tubes")
     * @ORM\JoinColumn(name="dropOffId", referencedColumnName="id", onDelete="SET NULL")
     */
    private $dropOff;

    /**
     * Date/Time when Tube was processed for check-in by Check-in Technician.
     *
     * @var \DateTimeImmutable
     * @ORM\Column(name="checkedInAt", type="datetime_immutable", nullable=true)
     */
    private $checkedInAt;

    /**
     * Check-in Tech that processed this Tube during Check-In.
     *
     * @var string
     * @ORM\Column(name="checkedInBy", type="string", nullable=true)
     */
    private $checkedInBy;

    /**
     * @var \DateTimeImmutable
     * @ORM\Column(name="collectedAt", type="datetime", nullable=true)
     */
    private $collectedAt;

    public function __construct(?string $accessionId = null)
    {
        $this->accessionId = $accessionId;
        $this->status = self::STATUS_PRINTED;
    }

    public function __toString()
    {
        return $this->getAccessionId();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getAccessionId(): ?string
    {
        return $this->accessionId;
    }

    /**
     * @param string $accessionId
     */
    public function setAccessionId(?string $accessionId): void
    {
        $this->accessionId = $accessionId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function getTubeType(): ?string
    {
        return $this->tubeType;
    }

    /**
     * @param string $tubeType
     */
    public function setTubeType(?string $tubeType): void
    {
        $this->tubeType = $tubeType;
    }

    public function getParticipantGroup(): ?ParticipantGroup
    {
        return $this->participantGroup;
    }

    /**
     * Set the Participant Group scanned when returning this tube.
     */
    public function setParticipantGroup(ParticipantGroup $group): void
    {
        $this->participantGroup = $group;
    }

    /**
     * Set when Participant returned Tube at a Kiosk
     */
    public function setReturnedAt(?\DateTimeImmutable $returnedAt): void
    {
        $this->returnedAt = $returnedAt;
    }

    public function getReturnedAt(): ?\DateTimeImmutable
    {
        return $this->returnedAt;
    }

    public function getDropOff(): ?DropOff
    {
        return $this->dropOff;
    }

    /**
     * @param DropOff $dropOff
     */
    public function setDropOff(?DropOff $dropOff): void
    {
        $this->dropOff = $dropOff;
    }

    /**
     * Set when Check-in Tech processed the returned Tube.
     */
    public function setCheckedInAt(?\DateTimeImmutable $checkedInAt): void
    {
        $this->checkedInAt = $checkedInAt;
    }

    public function getCheckedInAt(): ?\DateTimeImmutable
    {
        return $this->checkedInAt;
    }

    public function getCheckedInBy(): ?string
    {
        return $this->checkedInBy;
    }

    public function setCheckedInBy(?string $checkedInBy): void
    {
        $this->checkedInBy = $checkedInBy;
    }

    public function setSpecimen(Specimen $specimen): void
    {
        $this->specimen = $specimen;
    }

    public function getSpecimen(): ?Specimen
    {
        return $this->specimen;
    }

    public function getCollectedAt(): ?\DateTimeInterface
    {
        return $this->collectedAt;
    }

    public function setCollectedAt(?\DateTimeInterface $collectedAt): void
    {
        $this->collectedAt = $collectedAt;
    }

    /**
     * When a Participant has returned this Tube with their Specimen inside.
     */
    public function markReturned(\DateTimeImmutable $returnedAt)
    {
        $this->setStatus(self::STATUS_RETURNED);
        $this->setReturnedAt($returnedAt);
    }

    /**
     * When a Participant has returned this Tube with their Specimen inside.
     */
    public function markCheckedIn(\DateTimeImmutable $checkedInAt, string $checkedInBy): void
    {
        $this->setStatus(self::STATUS_CHECKEDIN);
        $this->setCheckedInAt($checkedInAt);
        $this->setCheckedInBy($checkedInBy);
    }

    /**
     * When an issue was observed making this Tube's Specimen unviable for further research.
     */
    public function markRejected(): void
    {
        $this->setStatus(self::STATUS_REJECTED);
    }

    /**
     * @see markReturned() and other status methods
     */
    private function setStatus(string $status): void
    {
        if (!in_array($status, self::getValidStatuses())) {
            throw new \InvalidArgumentException('Invalid status');
        }

        $this->status = $status;
    }

    /**
     * @return string[]
     */
    private static function getValidStatuses(): array
    {
        return [
            'Label Printed' => self::STATUS_PRINTED,
            'Returned' => self::STATUS_RETURNED,
            'Checked-In' => self::STATUS_CHECKEDIN,
            'Rejected' => self::STATUS_REJECTED,
        ];
    }

    public function getStatusText(): string
    {
        return self::lookupStatusText($this->status);
    }

    public static function lookupStatusText(string $statusConstant): string
    {
        $statuses = array_flip(static::getValidStatuses());

        return $statuses[$statusConstant];
    }
}

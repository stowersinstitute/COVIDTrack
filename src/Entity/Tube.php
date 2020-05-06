<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Mapping\Annotation as Gedmo;

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
     * @ORM\Column(type="string")
     */
    private $status;

    /**
     * Date/Time when Tube was returned by the Participant.
     *
     * @var \DateTimeImmutable
     */
    private $returnedAt;

    /**
     * Date/Time when Tube was processed for check-in by Check-in Technician.
     *
     * @var \DateTimeImmutable
     */
    private $processedAt;

    public function __construct(string $accessionId)
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

    public function getAccessionId(): string
    {
        return $this->accessionId;
    }

    public function getStatus(): string
    {
        return $this->status;
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

    public function setSpecimen(Specimen $specimen): void
    {
        $this->specimen = $specimen;
    }

    public function getSpecimen(): ?Specimen
    {
        return $this->specimen;
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

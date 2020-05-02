<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Biological material collected from a study Participant.
 * Each Participant belongs to a Participant Group. The Specimen is associated
 * to the group instead of the participant to maintain some anonymity.
 *
 * @ORM\Entity(repositoryClass="App\Entity\SpecimenRepository")
 * @Gedmo\Loggable
 */
class Specimen
{
    use TimestampableEntity, SoftDeleteableEntity;

    const STATUS_CREATED = "CREATED";
    const STATUS_PENDING = "PENDING";
    const STATUS_IN_PROCESS = "IN_PROCESS";
    const STATUS_RESULTS = "RESULTS";
    const STATUS_COMPLETE = "COMPLETE";

    /**
     * @var int
     * @ORM\Id()
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * Unique public ID for referencing this specimen.
     *
     * @var string
     * @ORM\Column(name="accessionId", type="string")
     * @Gedmo\Versioned
     */
    private $accessionId;

    /**
     * Participant offering this specimen belongs to this Participant Group.
     *
     * @var ParticipantGroup
     * @ORM\ManyToOne(targetEntity="App\Entity\ParticipantGroup", inversedBy="specimens")
     * @ORM\JoinColumn(name="participantGroupId", referencedColumnName="id")
     */
    private $participantGroup;

    /**
     * @var WellPlate
     * @ORM\ManyToOne(targetEntity="App\Entity\WellPlate", inversedBy="specimens")
     */
    private $wellPlate;

    /**
     * Time when collected or received.
     *
     * @var \DateTime
     * @ORM\Column(name="collectedAt", type="datetime", nullable=true)
     * @Gedmo\Versioned
     */
    private $collectedAt;

    /**
     * @var string
     * @ORM\Column(type="string")
     * @Gedmo\Versioned
     */
    private $status;

    public function __construct(string $accessionId, ParticipantGroup $group)
    {
        $this->accessionId = $accessionId;
        $this->participantGroup = $group;

        $this->status = self::STATUS_CREATED;
        $this->createdAt = new \DateTime();
    }

    public function __toString()
    {
        return $this->getAccessionId();
    }

    /**
     * Convert audit log field changes from internal format to human-readable format.
     *
     * Audit Logging tracks field/value changes using entity property names
     * and values like this:
     *
     *     [
     *         "status" => "IN_PROCESS", // STATUS_IN_PROCESS constant value
     *         "createdAt" => \DateTime(...),
     *     ]
     *
     * This method should convert the changes to human-readable values like this:
     *
     *     [
     *         "Status" => "In Process",
     *         "Created At" => \DateTime(...), // Frontend can custom print with ->format(...)
     *     ]
     *
     * @param array $changes Keys are internal entity propertyNames, Values are internal entity values
     * @return mixed[] Keys are human-readable field names, Values are human-readable values
     */
    public static function makeHumanReadableAuditLogFieldChanges(array $changes): array
    {
        $keyConverter = [
            // Specimen.propertyNameHere => Human-Readable Description
            'accessionId' => 'Accession ID',
            'collectedAt' => 'Collected At',
            'status' => 'Status',
            'createdAt' => 'Created At',
        ];

        /**
         * Keys are array key from $changes
         * Values are callbacks to convert $changes[$key] value
         */
        $valueConverter = [
            // Convert STATUS_* constants into human-readable text
            'status' => function($value) {
                return self::lookupStatusText($value);
            }
        ];

        $return = [];
        foreach ($changes as $fieldId => $value) {
            // If mapping fieldId to human-readable string, use it
            // Else fallback to original fieldId
            $key = $keyConverter[$fieldId] ?? $fieldId;

            // If mapping callback defined for fieldId, use it
            // Else fallback to current value
            $value = isset($valueConverter[$fieldId]) ? $valueConverter[$fieldId]($value) : $value;

            $return[$key] = $value;
        }

        return $return;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getAccessionId(): string
    {
        return $this->accessionId;
    }

    public function getParticipantGroup(): ParticipantGroup
    {
        return $this->participantGroup;
    }

    public function setParticipantGroup(ParticipantGroup $group): void
    {
        $this->participantGroup = $group;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        if (!in_array($status, self::getFormStatuses())) {
            throw new \InvalidArgumentException('Invalid status');
        }

        $this->status = $status;
    }

    /**
     * @return string[]
     */
    public static function getFormStatuses(): array
    {
        return [
            'Created' => self::STATUS_CREATED,
            'Pending' => self::STATUS_PENDING,
            'In Process' => self::STATUS_IN_PROCESS,
            'Results' => self::STATUS_RESULTS,
            'Complete' => self::STATUS_COMPLETE,
        ];
    }

    public function getStatusText(): string
    {
        return self::lookupStatusText($this->status);
    }

    public static function lookupStatusText(string $statusConstant): string
    {
        $statuses = array_flip(static::getFormStatuses());

        return $statuses[$statusConstant];
    }

    public function getWellPlate(): ?WellPlate
    {
        return $this->wellPlate;
    }

    public function setWellPlate(?WellPlate $wellPlate): void
    {
        $this->wellPlate = $wellPlate;
    }

    public function getCollectedAt(): ?\DateTime
    {
        return $this->collectedAt ? clone $this->collectedAt : null;
    }

    public function setCollectedAt(?\DateTime $collectedAt): void
    {
        $this->collectedAt = $collectedAt ? clone $collectedAt : null;
    }
}

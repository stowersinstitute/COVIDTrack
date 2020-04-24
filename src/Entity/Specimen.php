<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Biological material collected from a study Participant during a Collection Event.
 * Each Participant belongs to a Participant Group. The Specimen is associated
 * to the group instead of the participant to maintain some anonymity.
 *
 * @ORM\Entity
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
     * Unique public ID for referencing this sample.
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
     * Collection Event during which this Specimen was collected.
     *
     * @var CollectionEvent
     * @ORM\ManyToOne(targetEntity="App\Entity\CollectionEvent", inversedBy="specimens")
     * @ORM\JoinColumn(name="collectionEventId", referencedColumnName="id")
     */
    private $collectionEvent;

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

    public function __construct(string $accessionId, ParticipantGroup $group, CollectionEvent $event)
    {
        $this->accessionId = $accessionId;
        $this->participantGroup = $group;
        $this->collectionEvent = $event;

        $this->status = self::STATUS_CREATED;
        $this->createdAt = new \DateTime();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getAccessionId(): string
    {
        return $this->accessionId;
    }

    public function setAccessionId(string $id): void
    {
        $this->accessionId = $id;
    }

    public function getParticipantGroup(): ParticipantGroup
    {
        return $this->participantGroup;
    }

    public function setParticipantGroup(ParticipantGroup $group): void
    {
        $this->participantGroup = $group;
    }

    public function getCollectionEvent(): CollectionEvent
    {
        return $this->collectionEvent;
    }

    public function setCollectionEvent(CollectionEvent $event): void
    {
        $this->collectionEvent = $event;
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

    public function getCollectedAt(): ?\DateTime
    {
        return $this->collectedAt ? clone $this->collectedAt : null;
    }

    public function setCollectedAt(?\DateTime $collectedAt): void
    {
        $this->collectedAt = $collectedAt ? clone $collectedAt : null;
    }
}

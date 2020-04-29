<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Population of Participants being studied.
 *
 * @ORM\Entity(repositoryClass="App\Entity\ParticipantGroupRepository")
 * @Gedmo\Loggable
 */
class ParticipantGroup
{
    public const MIN_PARTICIPANT_COUNT = 1;
    public const MAX_PARTICIPANT_COUNT = 65000;

    use TimestampableEntity, SoftDeleteableEntity;

    /**
     * @var int
     * @ORM\Id()
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * Unique public ID for referencing this group.
     *
     * @var string
     * @ORM\Column(name="accessionId", type="string")
     * @Gedmo\Versioned
     */
    private $accessionId;

    /**
     * Number of Participants in this group.
     *
     * @var integer
     * @ORM\Column(name="participantCount", type="smallint", options={"unsigned":true}, nullable=false)
     * @Gedmo\Versioned
     */
    private $participantCount;

    /**
     * Specimens collected belonging to members of this group. Specimens are
     * not associated with individual Participants, only the group.
     *
     * @var Specimen[]|ArrayCollection
     * @ORM\OneToMany(targetEntity="App\Entity\Specimen", mappedBy="participantGroup")
     */
    private $specimens;

    public function __construct(string $accessionId, int $participantCount)
    {
        $this->accessionId = $accessionId;
        $this->participantCount = $participantCount;
        $this->specimens = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function __toString()
    {
        return $this->accessionId;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getAccessionId(): string
    {
        return $this->accessionId;
    }

    public function getParticipantCount(): int
    {
        return $this->participantCount;
    }

    public function setParticipantCount(int $participantCount): void
    {
        $this->validateParticipantCount($participantCount);

        $this->participantCount = $participantCount;
    }

    /**
     * List of current Specimens.
     *
     * @return Specimen[]
     */
    public function getSpecimens(): array
    {
        return $this->specimens->getValues();
    }

    public function addSpecimen(Specimen $specimen): void
    {
        // TODO: Add de-duplicating logic
        $this->specimens->add($specimen);
    }

    /**
     * Ensure participantCount within acceptable range, else throw exception.
     */
    private function validateParticipantCount(int $count): void
    {
        $min = self::MIN_PARTICIPANT_COUNT;
        $max = self::MAX_PARTICIPANT_COUNT;

        if ($count <= $max && $count >= $min) {
            // Everything ok
            return;
        }

        throw new \OutOfBoundsException(sprintf('participantCount must be between %d and %d', $min, $max));
    }
}

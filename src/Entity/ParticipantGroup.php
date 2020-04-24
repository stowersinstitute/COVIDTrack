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
 * @ORM\Entity
 * @Gedmo\Loggable
 */
class ParticipantGroup
{
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
     * Specimens collected belonging to members of this group. Specimens are
     * not associated with individual Participants, only the group.
     *
     * @var Specimen[]|ArrayCollection
     * @ORM\OneToMany(targetEntity="App\Entity\Specimen", mappedBy="participantGroup")
     */
    private $specimens;

    public function __construct(string $accessionId)
    {
        $this->accessionId = $accessionId;
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
}

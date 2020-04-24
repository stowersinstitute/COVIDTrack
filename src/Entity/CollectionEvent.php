<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Date and Place when Group Participants will provide a new Specimen.
 *
 * @ORM\Entity
 * @Gedmo\Loggable
 */
class CollectionEvent
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
     * Human-readable description of this event
     *
     * @var string
     * @ORM\Column(type="string", nullable=true)
     * @Gedmo\Versioned
     */
    private $title;

    /**
     * Date when Specimens will be collected from Participants.
     *
     * @var \DateTime
     * @ORM\Column(name="collectionDate", type="date", nullable=true)
     * @Gedmo\Versioned
     */
    private $collectionDate;

    /**
     * Specimens anticipated to be collected.
     *
     * @var Specimen[]|ArrayCollection
     * @ORM\OneToMany(targetEntity="App\Entity\Specimen", mappedBy="collectionEvent")
     */
    private $specimens;

    public function __construct()
    {
        $this->specimens = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function __toString()
    {
        if (null !== $this->title) {
            return $this->title;
        }

        return $this->title ?: 'Created at ' . $this->createdAt->format("Y-m-d H:i:s");
    }

    public function getId(): int
    {
        return $this->id;
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

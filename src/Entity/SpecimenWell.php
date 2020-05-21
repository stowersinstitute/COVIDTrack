<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Holds metadata about the relationship of a Specimen housed on a Well Plate
 *
 * @ORM\Entity
 * @ORM\Table(name="specimen_wells")
 */
class SpecimenWell
{
    /**
     * Well Plate where this well is located.
     * @var WellPlate
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="App\Entity\WellPlate", inversedBy="wells")
     * @ORM\JoinColumn(name="well_plate_id", referencedColumnName="id", nullable=false, onDelete="cascade")
     */
    private $wellPlate;

    /**
     * Specimen held in this well.
     *
     * @var Specimen
     * @ORM\Id
     * @ORM\OneToOne(targetEntity="App\Entity\Specimen", inversedBy="well")
     * @ORM\JoinColumn(name="specimen_id", referencedColumnName="id", nullable=false, onDelete="cascade")
     */
    private $specimen;

    /**
     * @var int
     * @ORM\Column(name="position", type="smallint", options={"unsigned":true}, nullable=false)
     */
    private $position;

    public function __construct(WellPlate $plate, Specimen $specimen, int $position)
    {
        $this->wellPlate = $plate;
        $this->specimen = $specimen;
        $this->position = $position;
    }

    public function delete()
    {
        // Remove WellPlate relationship
        $wellPlate = null;
        if ($this->wellPlate) {
            $wellPlate = $this->wellPlate;
            $this->wellPlate = null;
        }

        // Remove Specimen relationship
        $specimen = null;
        if ($this->specimen) {
            $specimen = $this->specimen;
            $this->specimen = null;
        }

        // Cleanup entity-side
        if ($wellPlate) {
            $wellPlate->removeWell($this);
        }
        if ($specimen) {
            $specimen->removeFromWell();
        }
    }

    public function getWellPlate(): WellPlate
    {
        return $this->wellPlate;
    }

    public function getSpecimen(): Specimen
    {
        return $this->specimen;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}

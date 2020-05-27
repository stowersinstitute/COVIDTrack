<?php

namespace App\Entity;

use App\Util\EntityUtils;
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
     * @ORM\Column(name="position", type="smallint", options={"unsigned":true}, nullable=true)
     */
    private $position;

    public function __construct(WellPlate $plate, Specimen $specimen, int $position = null)
    {
        $this->wellPlate = $plate;
        $this->specimen = $specimen;
        $this->position = $position;
    }

    /**
     * Check whether this SpecimenWell and another SpecimenWell are the same object.
     */
    public function isSame(SpecimenWell $specimenWell): bool
    {
        $specimen = $specimenWell->getSpecimen();
        if (!EntityUtils::isSameEntity($specimen, $this->specimen)) {
            return false;
        }

        $wellPlate = $specimenWell->getWellPlate();
        if (!EntityUtils::isSameEntity($wellPlate, $this->wellPlate)) {
            return false;
        }

        return true;
    }

    public function delete()
    {
        // Remove WellPlate relationship
        /** @var WellPlate $wellPlate */
        $wellPlate = null;
        if ($this->wellPlate) {
            $wellPlate = $this->wellPlate;
            $this->wellPlate = null;
        }

        // Remove Specimen relationship
        /** @var Specimen $specimen */
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

    public function getWellPlate(): ?WellPlate
    {
        return $this->wellPlate;
    }

    public function getWellPlateBarcode(): ?string
    {
        return $this->wellPlate->getBarcode();
    }

    public function getSpecimen(): ?Specimen
    {
        return $this->specimen;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}

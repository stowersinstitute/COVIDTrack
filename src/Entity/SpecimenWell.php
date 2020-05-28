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
     * @var int
     * @ORM\Id()
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * Well Plate where this well is located.
     * @var WellPlate
     * @ORM\ManyToOne(targetEntity="App\Entity\WellPlate", inversedBy="wells")
     * @ORM\JoinColumn(name="well_plate_id", referencedColumnName="id", nullable=false, onDelete="cascade")
     */
    private $wellPlate;

    /**
     * Specimen held in this well.
     *
     * @var Specimen
     * @ORM\ManyToOne(targetEntity="App\Entity\Specimen", inversedBy="wells")
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

    public function getId(): ?int
    {
        return $this->id;
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

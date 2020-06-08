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
     * @ORM\ManyToOne(targetEntity="App\Entity\WellPlate", inversedBy="wells", fetch="EAGER")
     * @ORM\JoinColumn(name="well_plate_id", referencedColumnName="id", nullable=false, onDelete="cascade")
     */
    private $wellPlate;

    /**
     * Specimen held in this well.
     *
     * @var Specimen
     * @ORM\ManyToOne(targetEntity="App\Entity\Specimen", inversedBy="wells", fetch="EAGER")
     * @ORM\JoinColumn(name="specimen_id", referencedColumnName="id", nullable=false, onDelete="cascade")
     */
    private $specimen;

    /**
     * Result of qPCR testing Specimen contained in this well.
     *
     * @var SpecimenResultQPCR|null
     * @ORM\OneToOne(targetEntity="App\Entity\SpecimenResultQPCR", mappedBy="well")
     */
    private $resultQPCR;

    /**
     * Well number, 1 thru 96
     *
     * @var int
     * @ORM\Column(name="position", type="smallint", options={"unsigned":true}, nullable=true)
     */
    private $position;

    public function __construct(WellPlate $plate, Specimen $specimen, int $position = null)
    {
        $this->wellPlate = $plate;
        $plate->addWell($this);

        $this->specimen = $specimen;
        $specimen->addWell($this);

        $this->position = $position;
    }

    /**
     * Will generate a fake WellPlate if not given.
     * $position is optional.
     */
    public static function buildExample(Specimen $specimen, WellPlate $plate = null, int $position = null): self
    {
        $plate = null !== $plate ? $plate : WellPlate::buildExample('PLATE001');

        return new static($plate, $specimen, $position);
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

        if ($this->position != $specimenWell->getPosition()) {
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

    public function setPosition(int $position): void
    {
        if ($position <= 0) {
            throw new \InvalidArgumentException('Position must be greater than 0');
        }

        $this->position = $position;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    /**
     * @internal Do not call directly. Instead call new SpecimenResultQPCR($specimen);
     */
    public function setQPCRResult(?SpecimenResultQPCR $result)
    {
        if ($result && $this->resultQPCR) {
            throw new \InvalidArgumentException('Cannot assign new qPCR result when one already exists');
        }

        $this->resultQPCR = $result;

        if ($this->getSpecimen()) {
            $this->getSpecimen()->updateStatusWhenResultsSet();
        }
    }

    public function getResultQPCR(): ?SpecimenResultQPCR
    {
        return $this->resultQPCR;
    }
}

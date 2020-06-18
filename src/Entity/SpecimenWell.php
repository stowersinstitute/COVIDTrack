<?php

namespace App\Entity;

use App\Util\EntityUtils;
use Doctrine\ORM\Mapping as ORM;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Holds metadata about the relationship of a Specimen housed on a Well Plate
 *
 * @ORM\Entity
 * @ORM\Table(name="specimen_wells")
 */
class SpecimenWell
{
    /**
     * Regex to validate alphanumericPosition between A1 and H12
     */
    public const alphanumericPositionRegex = '/^([A-H])([2-9]|1[0-2]?)$/';

    public const minIntegerPosition = 1;
    public const maxIntegerPosition = 96;

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
     * Well position in alphanumeric format such as A1, B4, H12, etc.
     *
     * @var string
     * @ORM\Column(name="position_alphanumeric", type="string", length=255, nullable=true)
     */
    private $positionAlphanumeric;

    public function __construct(WellPlate $plate, Specimen $specimen, string $position = null)
    {
        self::mustBeValidAlphanumericPosition($position);
        $this->positionAlphanumeric = $position;

        $this->wellPlate = $plate;
        $plate->addWell($this);

        $this->specimen = $specimen;
        $specimen->addWell($this);
    }

    /**
     * Will generate a fake WellPlate if not given.
     * $position is optional.
     */
    public static function buildExample(Specimen $specimen, WellPlate $plate = null, string $position = null): self
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

        if ($this->positionAlphanumeric !== $specimenWell->getPositionAlphanumeric()) {
            return false;
        }

        return $specimenWell === $this;
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

    /**
     * Given a numeric position (beginning at 1), get its alphanumeric equivalent.
     * Supports a 96-well plate.
     *
     *   1  2  3  4  5
     * A
     * B
     * C             * ( Given 15, Output C5)
     */
    public static function positionAlphanumericFromInt(int $positionInt): string
    {
        if ($positionInt < static::minIntegerPosition) {
            throw new \InvalidArgumentException('Position must be => ' . static::minIntegerPosition);
        }
        if ($positionInt > static::maxIntegerPosition) {
            throw new \InvalidArgumentException('Position must be <= ' . static::maxIntegerPosition);
        }

        $numRows = 8; // A-H

        $row = $positionInt % $numRows;
        if ($row === 0) {
            $row = $numRows;
        }
        $column = ceil($positionInt / $numRows);
        if ($column === 0) {
            $column = 1;
        }

        $position = Coordinate::stringFromColumnIndex($row) . $column;

        static::mustBeValidAlphanumericPosition($position);

        return $position;
    }

    public function setPositionAlphanumeric(string $position): void
    {
        self::mustBeValidAlphanumericPosition($position);

        if ($this->wellPlate->hasWellAtPosition($position)) {
            throw new \InvalidArgumentException(sprintf('Position "%s" is already occupied', $position));
        }

        $this->positionAlphanumeric = $position;
    }

    /**
     * @throws \InvalidArgumentException When given invalid position
     */
    public static function mustBeValidAlphanumericPosition(?string $position): void
    {
        // Empty is OK
        if (null === $position || '' === $position) {
            return;
        }

        // A-H; 1-12
        preg_match(static::alphanumericPositionRegex, $position, $matches);

        if (count($matches) !== 3) {
            throw new \InvalidArgumentException('Invalid position');
        }
    }

    public function getPositionAlphanumeric(): ?string
    {
        return $this->positionAlphanumeric;
    }

    /**
     * Display condensed string with Well Plate Barcode and Well Position, if available.
     */
    public function getWellPlatePositionDisplayString(): string
    {
        $barcode = $this->getWellPlateBarcode();
        $output = $barcode;

        $position = $this->getPositionAlphanumeric();
        if ($position !== null) {
            $output .= ' / ' . $position;
        }

        return $output;
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

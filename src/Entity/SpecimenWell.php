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
     * Result of Antibody testing Specimen contained in this well.
     *
     * @var SpecimenResultAntibody|null
     * @ORM\OneToOne(targetEntity="App\Entity\SpecimenResultAntibody", mappedBy="well")
     */
    private $resultAntibody;

    /**
     * Any identifier that identifies this well. For example, a Biobank Tube ID.
     * Uniqueness is not enforced on this field.
     *
     * @var null|string
     * @ORM\Column(name="well_identifier", type="string", length=255, nullable=true)
     */
    private $wellIdentifier;

    /**
     * Well position in alphanumeric format.
     *
     * Supports positions with and without a left-padded 0.
     * For example, these positions are equal:
     *
     *     A1 === A01
     *     A5 === A05
     *     B6 === B06
     *     B11 === B11
     *
     * @var string
     * @ORM\Column(name="position_alphanumeric", type="string", length=255, nullable=true)
     */
    private $positionAlphanumeric;

    public function __construct(WellPlate $plate, Specimen $specimen, string $position = null)
    {
        if (!self::isValidPosition($position)) {
            throw new \InvalidArgumentException('Invalid well position');
        }
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

        if (false === $this->isAtPosition($specimenWell->getPositionAlphanumeric())) {
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

    public function getWellIdentifier(): ?string
    {
        return $this->wellIdentifier;
    }

    public function setWellIdentifier(?string $wellIdentifier): void
    {
        $this->wellIdentifier = $wellIdentifier;
    }

    /**
     * Given a numeric position (beginning at 1), get its alphanumeric
     * equivalent such as "B4".
     *
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

        return Coordinate::stringFromColumnIndex($row) . $column;
    }

    /**
     * Whether given position is valid for specifying the Well Position.
     */
    private static function isValidPosition(?string $position): bool
    {
        if ($position === null) {
            return true;
        }

        // Empty string not allowed
        if (strlen($position) === 0) {
            return false;
        }

        return true;
    }

    public function setPositionAlphanumeric(?string $position): void
    {
        if (!self::isValidPosition($position)) {
            throw new \InvalidArgumentException('Invalid well position');
        }

        if ($this->wellPlate->hasWellAtPosition($position)) {
            throw new \InvalidArgumentException(sprintf('Position "%s" is already occupied', $position));
        }

        $this->positionAlphanumeric = $position;
    }

    /**
     * Check whether two positions are the same.
     */
    public static function isSamePosition(?string $position1, ?string $position2): bool
    {
        // Having NO position is not the *same* position
        if (null === $position1 && null === $position2) {
            return false;
        }

        // Literal same are obviously the same
        if ($position1 === $position2) {
            return true;
        }

        // Support "G05" === "G5"
        $pattern = '/^[A-G]0[1-9]$/';
        $position1Normalized = $position1;
        if (preg_match($pattern, $position1)) {
            $position1Normalized = str_replace('0', '', $position1);
        }
        $position2Normalized = $position2;
        if (preg_match($pattern, $position2)) {
            $position2Normalized = str_replace('0', '', $position2);
        }
        if ($position1Normalized === $position2Normalized) {
            return true;
        }

        return false;
    }

    /**
     * Check if this well is at given position.
     */
    public function isAtPosition(?string $checkPosition): bool
    {
        if (!self::isValidPosition($checkPosition)) {
            throw new \InvalidArgumentException('Invalid well position');
        }

        return self::isSamePosition($this->getPositionAlphanumeric(), $checkPosition);
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

        // Add Barcode
        $parts = [$barcode];

        // Add Position
        $position = $this->getPositionAlphanumeric();
        if ($position !== null) {
            $parts[] = $position;
        }

        return implode(' / ', $parts);
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

    /**
     * @internal Do not call directly. Instead call new SpecimenResultAntibody($specimen);
     */
    public function setAntibodyResult(?SpecimenResultAntibody $result)
    {
        if ($result && $this->resultAntibody) {
            throw new \InvalidArgumentException('Cannot assign new Antibody result when one already exists');
        }

        $this->resultAntibody = $result;

        if ($this->getSpecimen()) {
            $this->getSpecimen()->updateStatusWhenResultsSet();
        }
    }

    public function getResultAntibody(): ?SpecimenResultAntibody
    {
        return $this->resultAntibody;
    }
}

<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use App\Traits\TimestampableEntity;

/**
 * 96-Well Plate with one Specimen per Well.
 *
 * @ORM\Entity(repositoryClass="App\Repository\WellPlateRepository")
 * @ORM\Table(name="well_plates")
 */
class WellPlate
{
    use TimestampableEntity;

    /**
     * @var int
     * @ORM\Id()
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * String encoded into the barcode that is physically on the Well Plate.
     *
     * @var string
     * @ORM\Column(name="barcode", type="string", length=255, nullable=false, unique=true)
     */
    private $barcode;

    /**
     * Where this Well Plate is physically stored.
     *
     * @var null|string
     * @ORM\Column(name="storage_location", type="string", length=255, nullable=true)
     */
    private $storageLocation;

    /**
     * Wells that contain Specimens.
     *
     * @var SpecimenWell[]|ArrayCollection
     * @ORM\OneToMany(targetEntity="App\Entity\SpecimenWell", mappedBy="wellPlate", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    private $wells;

    public function __construct(string $barcode)
    {
        $this->wells = new ArrayCollection();
        $this->setBarcode($barcode);
    }

    public static function buildExample(string $barcode = 'ABC100'): self
    {
        return new static($barcode);
    }

    public function __toString()
    {
        return $this->barcode;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBarcode(): string
    {
        return $this->barcode;
    }

    public function setBarcode(string $barcode)
    {
        $barcode = trim($barcode);
        if (strlen($barcode) === 0) {
            throw new \InvalidArgumentException('WellPlate barcode cannot be empty');
        }

        $this->barcode = $barcode;
    }

    public function getStorageLocation(): ?string
    {
        return $this->storageLocation;
    }

    public function setStorageLocation(?string $storageLocation): void
    {
        $this->storageLocation = $storageLocation;
    }

    /**
     * @internal Do not call directly. Instead use `new SpecimenWell($plate, $specimen, $position)`
     */
    public function addWell(SpecimenWell $well): void
    {
        // Same Well can't be added twice
        if ($this->hasWell($well)) {
            throw new \InvalidArgumentException('Cannot add same SpecimenWell to WellPlate multiple times');
        }

        // Prevent adding Wells at currently occupied positions
        $atPosition = $well->getPositionAlphanumeric();
        if ($atPosition && $this->hasWellAtPosition($atPosition)) {
            $wellAtPosition = $this->getWellAtPosition($atPosition);
            $specimenId = $wellAtPosition->getSpecimen()->getAccessionId();
            throw new \InvalidArgumentException(sprintf('Cannot add a new Well at Position %s. Well with Specimen "%s" already exists at that Position.', $atPosition, $specimenId));
        }

        $this->wells->add($well);
    }

    /**
     * Whether given Well is already associated with this Well Plate
     */
    public function hasWell(SpecimenWell $well): bool
    {
        foreach ($this->wells as $existingWell) {
            if ($existingWell->isSame($well)) {
                return true;
            }
        }

        return false;
    }

    public function hasWellAtPosition(?string $atPosition): bool
    {
        return (bool) $this->getWellAtPosition($atPosition);
    }

    public function getWellAtPosition(?string $atPosition): ?SpecimenWell
    {
        if (null === $atPosition) return null;

        foreach ($this->wells as $well) {
            if (SpecimenWell::isSamePosition($well->getPositionAlphanumeric(), $atPosition)) {
                return $well;
            }
        }

        return null;
    }

    /**
     * Return all Wells on this WellPlate, ordered by position.
     *
     * @return SpecimenWell[]
     */
    public function getWells(): array
    {
        $wells = $this->wells->getValues();

        // Sort by alphanumeric position
        usort($wells, function(SpecimenWell $a, SpecimenWell $b) {
            // NOTE: Uses "natural" sort
            return strnatcmp($a->getPositionAlphanumeric(), $b->getPositionAlphanumeric());
        });

        return $wells;
    }

    /**
     * @return Specimen[]
     */
    public function getSpecimens(): array
    {
        $specimens = [];

        foreach ($this->wells as $well) {
            $s = $well->getSpecimen();
            $specimens[$s->getAccessionId()] = $s;
        }

        return array_values($specimens);
    }
}

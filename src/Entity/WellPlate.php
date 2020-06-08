<?php

namespace App\Entity;

use App\Util\EntityUtils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use App\Traits\TimestampableEntity;

/**
 * Well Plate with one Specimen per Well
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
     * Wells that contain Specimens.
     *
     * @var SpecimenWell[]|ArrayCollection
     * @ORM\OneToMany(targetEntity="App\Entity\SpecimenWell", mappedBy="wellPlate", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"position" = "ASC"})
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

    /**
     * @internal Do not call directly. Instead use `new SpecimenWell($plate, $specimen, $position)`
     */
    public function addWell(SpecimenWell $well): void
    {
        if ($this->hasWell($well)) {
            // Abort adding
            return;
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

    /**
     * @return SpecimenWell[]
     */
    public function getWells(): array
    {
        return $this->wells->getValues();
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

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
     * @ORM\OneToMany(targetEntity="App\Entity\SpecimenWell", mappedBy="wellPlate", cascade={"persist"}, orphanRemoval=true)
     * @ORM\OrderBy({"position" = "ASC"})
     */
    private $wells;

    public function __construct(string $barcode)
    {
        $this->wells = new ArrayCollection();
        $this->barcode = $barcode;
    }

    public function __toString()
    {
        return $this->barcode;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getBarcode(): ?string
    {
        return $this->barcode;
    }

    public function setBarcode(string $barcode)
    {
        $this->barcode = $barcode;
    }

    /**
     * @return SpecimenWell[]
     */
    public function getWells(): array
    {
        return $this->wells->getValues();
    }

    public function removeSpecimen(Specimen $specimen): void
    {
        $removeKey = null;
        foreach ($this->wells as $key => $well) {
            if (EntityUtils::isSameEntity($specimen, $well->getSpecimen())) {
                $removeKey = $key;
                break;
            }
        }

        if ($removeKey !== null) {
            $this->wells->remove($removeKey);
        }
    }

    public function removeWell(SpecimenWell $wellToRemove): void
    {
        foreach ($this->wells as $key => $well) {
            if (EntityUtils::isSameEntity($wellToRemove, $well)) {
                $removeKey = $key;
                break;
            }
        }

        if ($removeKey !== null) {
            $this->wells->remove($removeKey);
        }
    }

    /**
     * @return Specimen[]
     */
    public function getSpecimens(): array
    {
        $specimens = [];

        foreach ($this->wells as $well) {
            $specimens[] = $well->getSpecimen();
        }

        return $specimens;
    }
}

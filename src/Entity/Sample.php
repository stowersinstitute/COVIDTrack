<?php


namespace App\Entity;


use Doctrine\ORM\Mapping as ORM;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Class Sample
 * @package App\Entity
 *
 * @ORM\Entity
 * @Gedmo\Loggable
 */
class Sample
{
    use TimestampableEntity, SoftDeleteableEntity;

    const STATUS_PENDING = "PENDING";
    const STATUS_IN_PROCESS = "IN_PROCESS";
    const STATUS_RESULTS = "RESULTS";
    const STATUS_COMPLETE = "COMPLETE";

    /**
     * @var int
     * @ORM\Id()
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     * @ORM\Column(type="string")
     * @Gedmo\Versioned
     */
    private $title;

    /**
     * @var string
     * @ORM\Column(type="string")
     * @Gedmo\Versioned
     */
    private $status;

    /**
     * @var WellPlate
     * @ORM\ManyToOne(targetEntity="App\Entity\WellPlate", inversedBy="samples")
     * @Gedmo\Versioned
     */
    private $wellPlate;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    private $wellPlateRow;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    private $wellPlateColumn;

    public function __construct()
    {
        $this->status = self::STATUS_PENDING;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return WellPlate
     */
    public function getWellPlate(): ?WellPlate
    {
        return $this->wellPlate;
    }

    /**
     * @param WellPlate $wellPlate
     */
    public function setWellPlate(?WellPlate $wellPlate): self
    {
        $this->wellPlate = $wellPlate;

        return $this;
    }

    /**
     * @return string
     */
    public function getWellPlateRow(): ?string
    {
        return $this->wellPlateRow;
    }

    /**
     * @param string $wellPlateRow
     */
    public function setWellPlateRow(?string $wellPlateRow): self
    {
        $this->wellPlateRow = $wellPlateRow;

        return $this;
    }

    /**
     * @return string
     */
    public function getWellPlateColumn(): ?string
    {
        return $this->wellPlateColumn;
    }

    /**
     * @param string $wellPlateColumn
     */
    public function setWellPlateColumn(?string $wellPlateColumn): self
    {
        $this->wellPlateColumn = $wellPlateColumn;

        return $this;
    }

    public static function getFormStatuses()
    {
        return [
            'Pending' => self::STATUS_PENDING,
            'In Process' => self::STATUS_IN_PROCESS,
            'Results' => self::STATUS_RESULTS,
            'Complete' => self::STATUS_COMPLETE,
        ];
    }
}
<?php


namespace App\Entity;


use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Class Wellplate
 * @package App\Entity
 *
 * @ORM\Entity
 * @Gedmo\Loggable
 */
class Wellplate
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
    private $barcode;

    /**
     * @var string
     * @ORM\Column(type="string")
     * @Gedmo\Versioned
     */
    private $status;

    /**
     * @var Sample[]|ArrayCollection
     * @ORM\OneToMany(targetEntity="App\Entity\Sample", mappedBy="wellpate")
     */
    private $samples;

    public function __construct()
    {
        $this->status = self::STATUS_PENDING;
        $this->samples = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->barcode;
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
    public function getBarcode(): ?string
    {
        return $this->barcode;
    }

    /**
     * @param string $barcode
     */
    public function setBarcode(string $barcode): void
    {
        $this->barcode = $barcode;
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
    public function setStatus(string $status): void
    {
        $this->status = $status;
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

    /**
     * @return Sample[]|ArrayCollection
     */
    public function getSamples()
    {
        return $this->samples;
    }
}
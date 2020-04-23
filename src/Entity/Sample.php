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
     * @var Wellplate
     * @ORM\ManyToOne(targetEntity="App\Entity\Wellplate", inversedBy="samples")
     * @Gedmo\Versioned
     */
    private $wellplate;

    /**
     * @var string
     * @ORM\Column(type="string", length=3, nullable=true)
     */
    private $wellplateRow;

    /**
     * @var string
     * @ORM\Column(type="string", length=3, nullable=true)
     */
    private $wellplatecolumn;

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
    public function setTitle(string $title): void
    {
        $this->title = $title;
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

    /**
     * @return Wellplate
     */
    public function getWellplate(): ?Wellplate
    {
        return $this->wellplate;
    }

    /**
     * @param Wellplate $wellplate
     */
    public function setWellplate(?Wellplate $wellplate): void
    {
        $this->wellplate = $wellplate;
    }

    /**
     * @return string
     */
    public function getWellplateRow(): ?string
    {
        return $this->wellplateRow;
    }

    /**
     * @param string $wellplateRow
     */
    public function setWellplateRow(?string $wellplateRow): void
    {
        $this->wellplateRow = $wellplateRow;
    }

    /**
     * @return string
     */
    public function getWellplatecolumn(): ?string
    {
        return $this->wellplatecolumn;
    }

    /**
     * @param string $wellplatecolumn
     */
    public function setWellplatecolumn(?string $wellplatecolumn): void
    {
        $this->wellplatecolumn = $wellplatecolumn;
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
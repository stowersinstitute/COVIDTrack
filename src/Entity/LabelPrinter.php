<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Network connected label printer
 *
 * @ORM\Entity
 * @ORM\Table(name="label_printers")
 */
class LabelPrinter
{
    /**
     * Should match default value of param "resolution" at ZplBuilder::__construct()
     */
    const DEFAULT_DPI = 203;

    /**
     * @var integer
     *
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string")
     */
    private $title;

    /**
     * Hostname (preferred) or IP address of the printer
     *
     * @var string
     *
     * @ORM\Column(name="host", type="string")
     */
    private $host;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    private $description;

    /**
     * @var int
     *
     * @ORM\Column(name="dpi", type="integer", nullable=true)
     */
    private $dpi;

    /**
     * @var float
     *
     * @ORM\Column(name="media_width_in", type="float")
     */
    private $mediaWidthIn;

    /**
     * @var float
     *
     * @ORM\Column(name="media_height_in", type="float")
     */
    private $mediaHeightIn;

    /**
     * @var bool
     *
     * @ORM\Column(name="is_active", type="boolean")
     */
    private $isActive;

    public function __construct()
    {
        $this->isActive = true;
        $this->dpi = self::DEFAULT_DPI;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->title;
    }

    /**
     * @return int
     */
    public function getId(): ?int
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
    public function getHost(): ?string
    {
        return $this->host;
    }

    /**
     * @param string $host
     */
    public function setHost(string $host): void
    {
        $this->host = $host;
    }

    /**
     * @return string
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getDpi(): int
    {
        return $this->dpi;
    }

    public function setDpi(int $dpi): void
    {
        $this->dpi = $dpi;
    }

    public function getMediaWidthIn(): ?float
    {
        return $this->mediaWidthIn;
    }

    public function setMediaWidthIn(float $mediaWidthIn): void
    {
        $this->mediaWidthIn = $mediaWidthIn;
    }

    public function getMediaHeightIn(): ?float
    {
        return $this->mediaHeightIn;
    }

    public function setMediaHeightIn(float $mediaHeightIn): void
    {
        $this->mediaHeightIn = $mediaHeightIn;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * @param bool $isActive
     */
    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }
}
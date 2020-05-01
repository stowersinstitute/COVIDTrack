<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Network connected label printer
 *
 * @ORM\Entity
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
     * @ORM\Column()
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(type="string", name="title")
     */
    private $title;

    /**
     * Hostname (preferred) or IP address of the printer
     *
     * @var string
     *
     * @ORM\Column(type="string", name="host")
     */
    private $host;

    /**
     * @var string
     *
     * @ORM\Column(type="text", name="description", nullable=true)
     */
    private $description;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="dpi", nullable=true)
     */
    private $dpi;

    /**
     * @var float
     *
     * @ORM\Column(type="float", name="mediaWidth")
     */
    private $mediaWidth;

    /**
     * @var float
     *
     * @ORM\Column(type="float", name="mediaHeight")
     */
    private $mediaHeight;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", name="isActive")
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

    public function getMediaWidth(): float
    {
        return $this->mediaWidth;
    }

    public function setMediaWidth(float $mediaWidth): void
    {
        $this->mediaWidth = $mediaWidth;
    }

    public function getMediaHeight(): float
    {
        return $this->mediaHeight;
    }

    public function setMediaHeight(float $mediaHeight): void
    {
        $this->mediaHeight = $mediaHeight;
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
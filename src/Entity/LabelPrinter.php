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
     * @ORM\Column(type="string")
     */
    private $title;

    /**
     * Hostname (preferred) or IP address of the printer
     *
     * @var string
     *
     * @ORM\Column(type="string")
     */
    private $host;

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private $description;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    private $dpi;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean")
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
    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
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
    public function setHost(string $host): self
    {
        $this->host = $host;

        return $this;
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
    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getDpi(): int
    {
        return $this->dpi;
    }

    public function setDpi(int $dpi): self
    {
        $this->dpi = $dpi;

        return $this;
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
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use App\Traits\TimestampableEntity;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Result of analyzing a Specimen. Subclass and specify unique fields.
 *
 * @ORM\Entity
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="discr", type="string")
 * @ORM\DiscriminatorMap({
 *     "qpcr" = "SpecimenResultQPCR",
 *     "ddpcr" = "SpecimenResultDDPCR",
 *     "sequencing" = "SpecimenResultSequencing",
 * })
 */
abstract class SpecimenResult
{
    use TimestampableEntity, SoftDeleteableEntity;

    /**
     * @var int
     * @ORM\Id()
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * Specimen analyzed to generate these results.
     *
     * @var ParticipantGroup
     * @ORM\ManyToOne(targetEntity="App\Entity\Specimen", inversedBy="results")
     * @ORM\JoinColumn(name="specimenId", referencedColumnName="id", onDelete="CASCADE")
     */
    private $specimen;

    /**
     * Whether this analysis result encountered a failure.
     *
     * @var bool
     * @ORM\Column(name="isFailure", type="boolean")
     */
    private $isFailure = false;

    public function __construct(Specimen $specimen)
    {
        $specimen->addResult($this);
        $this->specimen = $specimen;
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSpecimen(): Specimen
    {
        return $this->specimen;
    }

    public function setIsFailure(bool $bool): void
    {
        $this->isFailure = $bool;
    }

    public function isFailure(): bool
    {
        return $this->isFailure;
    }
}

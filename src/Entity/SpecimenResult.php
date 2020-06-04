<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Traits\SoftDeleteableEntity;
use App\Traits\TimestampableEntity;

/**
 * Result of analyzing a Specimen. Subclass and specify unique fields.
 *
 * @ORM\Entity
 * @ORM\Table(
 *     name="specimen_results",
 *     indexes={
 *         @ORM\Index(name="specimen_results_created_at_idx", columns={"created_at"}),
 *         @ORM\Index(name="specimen_results_conclusion_idx", columns={"conclusion"})
 *     },
 * )
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="discr", type="string")
 * @ORM\DiscriminatorMap({
 *     "qpcr" = "SpecimenResultQPCR",
 * })
 */
abstract class SpecimenResult
{
    use TimestampableEntity, SoftDeleteableEntity;

    /**
     * @var int
     * @ORM\Id()
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * Specimen analyzed to generate these results.
     *
     * @var Specimen
     * @ORM\ManyToOne(targetEntity="App\Entity\Specimen", inversedBy="results")
     * @ORM\JoinColumn(name="specimen_id", referencedColumnName="id", onDelete="CASCADE")
     * @deprecated Will be removed
     */
    private $specimen;

    /**
     * Whether this analysis result encountered a failure.
     *
     * @var bool
     * @ORM\Column(name="is_failure", type="boolean")
     */
    private $isFailure = false;

    /**
     * Subclass should define its own annotations for how it maps to SpecimenWell,
     * and return SpecimenWell from it.
     *
     * @return SpecimenWell
     */
    abstract public function getWell(): SpecimenWell;

    /**
     * Subclass should decide how to return the related Specimen,
     * usually through the SpecimenWell.
     *
     * For example:
     *
     *     return $this->getWell()->getSpecimen();
     *
     * @return Specimen
     */
    abstract public function getSpecimen(): Specimen;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getReportedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSpecimenAccessionId(): string
    {
        return $this->getSpecimen()->getAccessionId();
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

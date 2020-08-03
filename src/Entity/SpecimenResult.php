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
 *     "antibody" = "SpecimenResultAntibody",
 * })
 */
abstract class SpecimenResult
{
    use TimestampableEntity, SoftDeleteableEntity;

    // When analysis did not find evidence of what it was searching for.
    const CONCLUSION_NEGATIVE = "NEGATIVE";

    // When analysis found evidence of what it was searching for.
    const CONCLUSION_POSITIVE = "POSITIVE";

    // When result is not negative, but not certainly positive.
    // May be because of a dirty sample, or questionable analysis, or similar.
    const CONCLUSION_NON_NEGATIVE = "NON-NEGATIVE";

    /**
     * @var int
     * @ORM\Id()
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * Whether this analysis result encountered a failure.
     *
     * @var bool
     * @ORM\Column(name="is_failure", type="boolean")
     */
    private $isFailure = false;

    /**
     * Conclusion about the Specimen based on analyzing it.
     *
     * @var string
     * @ORM\Column(name="conclusion", type="string", length=255)
     */
    private $conclusion;

    /**
     * Subclass should define its own annotations for how it maps to SpecimenWell,
     * and return SpecimenWell from it.
     */
    abstract public function getWell(): ?SpecimenWell;

    /**
     * Subclass should decide how to return the related Specimen,
     * usually through the SpecimenWell.
     *
     * For example:
     *
     *     return $this->getWell()->getSpecimen();
     */
    abstract public function getSpecimen(): Specimen;

    /**
     * Subclass should decide how to return the related WellPlate,
     * usually through the SpecimenWell.
     *
     * For example:
     *
     *     return $this->getWell()->getWellPlate();
     */
    abstract public function getWellPlate(): ?WellPlate;

    /**
     * Subclass should decide how to return the related Well Position,
     * usually through the SpecimenWell.
     *
     * For example:
     *
     *     return $this->getWell()->getPositionAlphanumeric();
     */
    abstract public function getWellPosition(): ?string;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getConclusion(): string
    {
        return $this->conclusion;
    }

    public function setConclusion(string $conclusion): void
    {
        if (!static::isValidConclusion($conclusion)) {
            throw new \InvalidArgumentException('Cannot set invalid result Conclusion');
        }

        $this->conclusion = $conclusion;
    }

    public static function isValidConclusion(string $conclusion): bool
    {
        return in_array($conclusion, static::getFormConclusions());
    }

    public function getConclusionText(): string
    {
        $conclusions = array_flip(static::getFormConclusions());

        return $conclusions[$this->getConclusion()] ?? '';
    }

    /**
     * Overwrite method to return Conclusions supported by each subclass.
     *
     * @return string[]
     */
    public static function getFormConclusions(): array
    {
        return [
            'Negative' => static::CONCLUSION_NEGATIVE,
            'Non-Negative' => static::CONCLUSION_NON_NEGATIVE,
            'Positive' => static::CONCLUSION_POSITIVE,
        ];
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

    public function getWellPlateBarcode(): ?string
    {
        return $this->getWellPlate() ? $this->getWellPlate()->getBarcode() : null;
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

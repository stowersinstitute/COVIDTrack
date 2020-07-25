<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Result of performing qPCR analysis on Specimen.
 *
 * @ORM\Entity(repositoryClass="App\Repository\SpecimenResultQPCRRepository")
 * NOTE: (a)ORM\Table defined on parent class
 */
class SpecimenResultQPCR extends SpecimenResult
{
    // When result indicates Participant should obtain CLIA-based COVID test.
    // Testing confidence is low, but leans towards viral RNA being present.
    const CONCLUSION_RECOMMENDED = "RECOMMENDED";

    /**
     * Well analyzed to derive this result
     *
     * @var SpecimenWell
     * @ORM\OneToOne(targetEntity="App\Entity\SpecimenWell", inversedBy="resultQPCR", fetch="EAGER")
     * @ORM\JoinColumn(name="specimen_well_id", referencedColumnName="id")
     */
    private $well;

    /**
     * Specimen analyzed.
     *
     * @var Specimen
     * @ORM\ManyToOne(targetEntity="App\Entity\Specimen", inversedBy="resultsQPCR")
     * @ORM\JoinColumn(name="specimen_id", referencedColumnName="id")
     */
    private $specimen;

    /**
     * @var null|string
     * @ORM\Column(name="ct1", type="string", length=255, nullable=true)
     */
    private $ct1;

    /**
     * @var null|string
     * @ORM\Column(name="ct1_amp_score", type="string", length=255, nullable=true)
     */
    private $ct1AmpScore;

    /**
     * @var null|string
     * @ORM\Column(name="ct2", type="string", length=255, nullable=true)
     */
    private $ct2;

    /**
     * @var null|string
     * @ORM\Column(name="ct2_amp_score", type="string", length=255, nullable=true)
     */
    private $ct2AmpScore;

    /**
     * @var null|string
     * @ORM\Column(name="ct3", type="string", length=255, nullable=true)
     */
    private $ct3;

    /**
     * @var null|string
     * @ORM\Column(name="ct3_amp_score", type="string", length=255, nullable=true)
     */
    private $ct3AmpScore;

    /**
     * @param string       $conclusion SpecimenResultQPCR::CONCLUSION_* constant
     */
    public function __construct(SpecimenWell $well, string $conclusion)
    {
        parent::__construct();

        if (!$well->getSpecimen()) {
            throw new \InvalidArgumentException('SpecimenWell must have a Specimen to associate SpecimenResultQPCR');
        }
        $this->specimen = $well->getSpecimen();
        $this->specimen->addQPCRResult($this);

        // Setup relationship between SpecimenWell <==> SpecimenResultsQPCR
        $this->well = $well;
        $well->setQPCRResult($this);

        $this->setConclusion($conclusion);
    }

    public function getWell(): SpecimenWell
    {
        return $this->well;
    }

    public function getSpecimen(): Specimen
    {
        return $this->specimen;
    }

    public function getWellPlate(): WellPlate
    {
        return $this->well->getWellPlate();
    }

    public function getWellPosition(): string
    {
        return $this->well->getPositionAlphanumeric() ?: '';
    }

    public function setConclusion(string $conclusion): void
    {
        parent::setConclusion($conclusion);

        // Specimen recommendation depends on conclusion
        $this->getSpecimen()->recalculateCliaTestingRecommendation();
    }

    /**
     * @return string[]
     */
    public static function getFormConclusions(): array
    {
        return [
            'Negative' => self::CONCLUSION_NEGATIVE,
            'Non-Negative' => self::CONCLUSION_NON_NEGATIVE,
            'Recommended' => self::CONCLUSION_RECOMMENDED,
            'Positive' => self::CONCLUSION_POSITIVE,
        ];
    }

    public function getCT1(): ?string
    {
        return $this->ct1;
    }

    public function setCT1(?string $ct): void
    {
        $this->ct1 = $ct;
    }

    public function getCT1AmpScore(): ?string
    {
        return $this->ct1AmpScore;
    }

    public function setCT1AmpScore(?string $ampScore): void
    {
        $this->ct1AmpScore = $ampScore;
    }

    public function getCT2(): ?string
    {
        return $this->ct2;
    }

    public function setCT2(?string $ct): void
    {
        $this->ct2 = $ct;
    }

    public function getCT2AmpScore(): ?string
    {
        return $this->ct2AmpScore;
    }

    public function setCT2AmpScore(?string $ampScore): void
    {
        $this->ct2AmpScore = $ampScore;
    }

    public function getCT3(): ?string
    {
        return $this->ct3;
    }

    public function setCT3(?string $ct): void
    {
        $this->ct3 = $ct;
    }

    public function getCT3AmpScore(): ?string
    {
        return $this->ct3AmpScore;
    }

    public function setCT3AmpScore(?string $ampScore): void
    {
        $this->ct3AmpScore = $ampScore;
    }
}

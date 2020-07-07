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
            'Inconclusive' => self::CONCLUSION_INCONCLUSIVE,
            'Recommended' => self::CONCLUSION_RECOMMENDED,
            'Positive' => self::CONCLUSION_POSITIVE,
        ];
    }
}

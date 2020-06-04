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
    // When result is not yet available.
    const CONCLUSION_PENDING = "PENDING";

    // When result did not find evidence of viral DNA in Specimen.
    const CONCLUSION_NEGATIVE = "NEGATIVE";

    // When result indicates Participant should obtain CLIA-based COVID test.
    // Testing confidence is high, strongly leans towards viral RNA being present.
    const CONCLUSION_POSITIVE = "POSITIVE";

    // When result indicates Participant should obtain CLIA-based COVID test.
    // Testing confidence is low, but leans towards viral RNA being present.
    const CONCLUSION_RECOMMENDED = "RECOMMENDED";

    // When result could not be determined positive or negative.
    const CONCLUSION_INCONCLUSIVE = "INCONCLUSIVE";

    /**
     * Well analyzed to derive this result
     *
     * @var SpecimenWell
     * @ORM\OneToOne(targetEntity="App\Entity\SpecimenWell", inversedBy="resultQPCR")
     * @ORM\JoinColumn(name="specimen_well_id", referencedColumnName="id")
     */
    private $well;

    /**
     * Conclusion about presence of virus SARS-CoV-2 in specimen.
     *
     * @var string
     * @ORM\Column(name="conclusion", type="string")
     */
    private $conclusion;

    /**
     * @param string       $conclusion SpecimenResultQPCR::CONCLUSION_* constant
     */
    public function __construct(SpecimenWell $well, string $conclusion)
    {
        parent::__construct();

        // Setup relationship between SpecimenWell <==> SpecimenResultsQPCR
        $this->well = $well;
        $well->setQPCRResult($this);

        if (!self::isValidConclusion($conclusion)) {
            throw new \InvalidArgumentException('Cannot set invalid qPCR Result Conclusion');
        }
        $this->conclusion = $conclusion;

        // Specimen recommendation depends on conclusion
        $this->getSpecimen()->recalculateCliaTestingRecommendation();
    }

    public function getWell(): SpecimenWell
    {
        return $this->well;
    }

    public function getSpecimen(): Specimen
    {
        return $this->well->getSpecimen();
    }

    public function getConclusion(): string
    {
        return $this->conclusion;
    }

    public static function isValidConclusion(string $conclusion): bool
    {
        return in_array($conclusion, self::getFormConclusions());
    }

    public function getConclusionText(): string
    {
        $conclusions = array_flip(self::getFormConclusions());

        return $conclusions[$this->conclusion] ?? '';
    }

    /**
     * @return string[]
     */
    public static function getFormConclusions(): array
    {
        return [
            'Pending' => self::CONCLUSION_PENDING,
            'Negative' => self::CONCLUSION_NEGATIVE,
            'Inconclusive' => self::CONCLUSION_INCONCLUSIVE,
            'Recommended' => self::CONCLUSION_RECOMMENDED,
            'Positive' => self::CONCLUSION_POSITIVE,
        ];
    }
}

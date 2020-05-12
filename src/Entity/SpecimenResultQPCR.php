<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Result of performing qPCR analysis on Specimen.
 *
 * @ORM\Entity
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
     * Conclusion about presence of virus SARS-CoV-2 in specimen.
     *
     * @var string
     * @ORM\Column(name="conclusion", type="string")
     */
    private $conclusion;

    public function __construct(Specimen $specimen)
    {
        $this->conclusion = self::CONCLUSION_PENDING;

        parent::__construct($specimen);
    }

    public function getConclusion(): string
    {
        return $this->conclusion;
    }

    public function setConclusion(string $conclusion): void
    {
        if (!in_array($conclusion, self::getFormConclusions())) {
            throw new \InvalidArgumentException('Tried setting invalid Conclusion');
        }

        $this->conclusion = $conclusion;

        // Specimen recommendation depends on conclusion
        $this->getSpecimen()->recalculateCliaTestingRecommendation();
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

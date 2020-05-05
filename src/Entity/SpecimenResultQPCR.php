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
    // When results are not yet available. Could be because no results entered
    // or Specimen required re-testing.
    const CONCLUSION_PENDING = "PENDING";

    // When result did not find evidence of viral DNA in Specimen.
    const CONCLUSION_NEGATIVE = "NEGATIVE";

    // When result indicates Participant should obtain CLIA-based COVID test.
    // Likely because viral RNA was present in their Specimen.
    const CONCLUSION_POSITIVE = "RECOMMENDED";

    // When result are not positive or negative
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
            'Awaiting Results' => self::CONCLUSION_PENDING,
            'Negative' => self::CONCLUSION_NEGATIVE,
            'Positive' => self::CONCLUSION_POSITIVE,
            'Inconclusive' => self::CONCLUSION_INCONCLUSIVE,
        ];
    }
}

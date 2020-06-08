<?php

namespace App\Report;

use App\Entity\Specimen;

/**
 * Recommendation printed as text, based on testing results of all Specimen
 * included in a time frame.
 *
 * Usage:
 *
 *     $specimensInRange = ...; Specimen[]
 *     $recommendation = GroupTestingRecommendation::createForSpecimens($specimensInRange);
 *     echo $recommendation;
 */
class GroupTestingRecommendation
{
    /**
     * Text to display on Participant Group Report
     * @var string
     */
    private $recommendationText;

    public function __construct(string $recommendationText)
    {
        $this->recommendationText = $recommendationText;
    }

    public function __toString()
    {
        return $this->recommendationText;
    }

    /**
     * @param Specimen[] $specimens All Specimens included in desired period
     */
    public static function createForSpecimens(array $specimens): self
    {
        // Calculate count for each Specimen CLIA testing recommendation value
        $initial = [
            Specimen::CLIA_REC_YES => 0,
            Specimen::CLIA_REC_PENDING => 0,
            Specimen::CLIA_REC_NO => 0,
        ];
        $count = array_reduce($specimens, function (array $carry, Specimen $s) {
            $carry[$s->getCliaTestingRecommendation()]++;
            return $carry;
        }, $initial);

        // If any specimen recommended for testing, whole group must be notified for CLIA testing
        if ($count[Specimen::CLIA_REC_YES] > 0) {
            $text = Specimen::lookupCliaTestingRecommendationText(Specimen::CLIA_REC_YES);
        }
        // If awaiting at least one result, group results still pending
        else if ($count[Specimen::CLIA_REC_PENDING] > 0) {
            $text = Specimen::lookupCliaTestingRecommendationText(Specimen::CLIA_REC_PENDING);
        }
        // If all report no testing recommended, CLIA testing not necessary
        else if ($count[Specimen::CLIA_REC_NO] > 0) {
            $text = Specimen::lookupCliaTestingRecommendationText(Specimen::CLIA_REC_NO);
        }
        // Collection of Specimen has no recommendation
        else {
            $text = '-';
        }

        return new static($text);
    }
}

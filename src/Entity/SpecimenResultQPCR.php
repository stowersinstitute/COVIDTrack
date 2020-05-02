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
    const CONCLUSION_POSITIVE = "POSITIVE";
    const CONCLUSION_NEGATIVE = "NEGATIVE";
    const CONCLUSION_INCONCLUSIVE = "INCONCLUSIVE";
    const CONCLUSION_INVALID = "INVALID";

    /**
     * Conclusion about presence of virus SARS-CoV-2 in specimen.
     *
     * @var string
     * @ORM\Column(name="conclusion", type="string")
     */
    private $conclusion;

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
            'Negative' => self::CONCLUSION_NEGATIVE,
            'Inconclusive' => self::CONCLUSION_INCONCLUSIVE,
            'Invalid' => self::CONCLUSION_INVALID,
            'Positive' => self::CONCLUSION_POSITIVE,
        ];
    }
}

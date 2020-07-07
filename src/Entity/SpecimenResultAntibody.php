<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Result of analyzing presence of antibodies in Specimen.
 *
 * @ORM\Entity(repositoryClass="App\Repository\SpecimenResultAntibodyRepository")
 * NOTE: (a)ORM\Table defined on parent class
 */
class SpecimenResultAntibody extends SpecimenResult
{
    // When result did not find evidence of antibodies in Specimen
    const CONCLUSION_QUANT_NEGATIVE_TEXT = "NEGATIVE";
    const CONCLUSION_QUANT_NEGATIVE_INT = 0;

    const CONCLUSION_QUANT_PARTIAL_TEXT = "PARTIAL";
    const CONCLUSION_QUANT_PARTIAL_INT = 1;

    const CONCLUSION_QUANT_WEAK_TEXT = "WEAK";
    const CONCLUSION_QUANT_WEAK_INT = 2;

    const CONCLUSION_QUANT_STRONG_TEXT = "STRONG";
    const CONCLUSION_QUANT_STRONG_INT = 3;

    /**
     * Well analyzed to derive this result
     *
     * @var SpecimenWell
     * @ORM\OneToOne(targetEntity="App\Entity\SpecimenWell", inversedBy="resultAntibody", fetch="EAGER")
     * @ORM\JoinColumn(name="specimen_well_id", referencedColumnName="id")
     */
    private $well;

    /**
     * Specimen analyzed.
     *
     * @var Specimen
     * @ORM\ManyToOne(targetEntity="App\Entity\Specimen", inversedBy="resultsAntibody")
     * @ORM\JoinColumn(name="specimen_id", referencedColumnName="id")
     */
    private $specimen;

    /**
     * Numerical representation of Conclusion.
     *
     * @var null|int
     * @ORM\Column(name="conclusion_quantitative", type="integer", nullable=true)
     */
    private $conclusionQuantitative;

    /**
     * @param string $conclusion CONCLUSION_* constant
     * @param null|int $conclusionQuantitative CONCLUSION_QUANT_*_INT value, called "Signal"
     */
    public function __construct(SpecimenWell $well, string $conclusion, ?int $conclusionQuantitative)
    {
        parent::__construct();

        if (!$well->getSpecimen()) {
            throw new \InvalidArgumentException('SpecimenWell must have a Specimen to associate SpecimenResultAntibody');
        }
        $this->specimen = $well->getSpecimen();
        $this->specimen->addAntibodyResult($this);

        // Setup relationship between SpecimenWell <==> SpecimenResultsAntibody
        $this->well = $well;
        $well->setAntibodyResult($this);

        $this->setConclusion($conclusion);
        $this->setConclusionQuantitative($conclusionQuantitative);
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

    public function getWellIdentifier(): string
    {
        $identifier = $this->well->getWellIdentifier();

        return $identifier !== null ? $identifier : '';
    }

    public function setWellIdentifier(?string $identifier): void
    {
        $this->getWell()->setWellIdentifier($identifier);
    }

    /**
     * @return string[]
     */
    public static function getFormConclusions(): array
    {
        return [
            'Negative' => self::CONCLUSION_NEGATIVE,
            'Positive' => self::CONCLUSION_POSITIVE,
            'Inconclusive' => self::CONCLUSION_INCONCLUSIVE,
        ];
    }

    /**
     * @return int[]
     */
    public static function getFormConclusionQuantitative(): array
    {
        $validValues = range(self::CONCLUSION_QUANT_NEGATIVE_INT, self::CONCLUSION_QUANT_STRONG_INT);

        $textLabels = array_map('strval', $validValues);
        $fieldValues = $validValues;

        return array_combine($textLabels, $fieldValues);
    }

    /**
     * Check if given value is a valid quantitative conclusion.
     *
     * @param mixed $value Explicitly does not use typehint. See code.
     * @return bool
     */
    public static function isValidConclusionQuantitative($value): bool
    {
        // NULL is allowed
        // Explicitly does not use a typehint AND
        // Explicitly checks NULL to denote between empty and 0
        if ($value === null) {
            return true;
        }

        // Must be integer/string that casts to itself
        if (!is_int($value) && !is_string($value)) {
            return false;
        }

        // Cast to int, since value must be integer to be stored
        $testValue = (int) $value;

        $valid = range(self::CONCLUSION_QUANT_NEGATIVE_INT, self::CONCLUSION_QUANT_STRONG_INT);

        return in_array($testValue, $valid);
    }

    public function setConclusionQuantitative(int $signal)
    {
        if (!self::isValidConclusionQuantitative($signal)) {
            throw new \InvalidArgumentException('Unknown signal value for setting quantitative Antibody result');
        }

        $this->conclusionQuantitative = $signal;
    }

    public function getConclusionQuantitative(): ?int
    {
        return $this->conclusionQuantitative;
    }
}

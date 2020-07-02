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
    const CONCLUSION_NEGATIVE_TEXT = "NEGATIVE";
    const CONCLUSION_NEGATIVE_INT = 0;

    const CONCLUSION_PARTIAL_TEXT = "PARTIAL";
    const CONCLUSION_PARTIAL_INT = 1;

    const CONCLUSION_WEAK_TEXT = "WEAK";
    const CONCLUSION_WEAK_INT = 2;

    const CONCLUSION_STRONG_TEXT = "STRONG";
    const CONCLUSION_STRONG_INT = 3;

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
     * Conclusion about presence of antibodies against SARS-CoV-2 virus.
     *
     * @var string
     * @ORM\Column(name="conclusion", type="string", length=255)
     */
    private $conclusion;

    /**
     * Numerical representation of Conclusion.
     *
     * @var null|int
     * @ORM\Column(name="conclusion_quantitative", type="integer", nullable=true)
     */
    private $conclusionQuantitative;

    /**
     * @param int $conclusionQuantitative Number representing conclusion, called "Signal"
     */
    public function __construct(SpecimenWell $well, int $conclusionQuantitative)
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

    public function getConclusion(): string
    {
        return $this->conclusion;
    }

    public function setConclusion(string $conclusion): void
    {
        if (!self::isValidConclusion($conclusion)) {
            throw new \InvalidArgumentException('Cannot set invalid Result Conclusion');
        }
        $this->conclusion = $conclusion;
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
            'Negative' => self::CONCLUSION_NEGATIVE_TEXT,
            'Partial' => self::CONCLUSION_PARTIAL_TEXT,
            'Weak' => self::CONCLUSION_WEAK_TEXT,
            'Strong' => self::CONCLUSION_STRONG_TEXT,
        ];
    }

    /**
     * @return int[]
     */
    public static function getFormConclusionQuantitative(): array
    {
        return [
            'Negative' => self::CONCLUSION_NEGATIVE_INT,
            'Partial' => self::CONCLUSION_PARTIAL_INT,
            'Weak' => self::CONCLUSION_WEAK_INT,
            'Strong' => self::CONCLUSION_STRONG_INT,
        ];
    }

    public function setConclusionQuantitative(int $number)
    {
        // TODO: Replace in AppAntibodyResultsFixtures
        // Each quantitative conclusion corresponds to a text conclusion
        $map = [
            self::CONCLUSION_NEGATIVE_INT => self::CONCLUSION_NEGATIVE_TEXT,
            self::CONCLUSION_PARTIAL_INT => self::CONCLUSION_PARTIAL_TEXT,
            self::CONCLUSION_WEAK_INT => self::CONCLUSION_WEAK_TEXT,
            self::CONCLUSION_STRONG_INT => self::CONCLUSION_STRONG_TEXT,
        ];

        $conclusion = $map[$number];
        if ($number !== null && !isset($conclusion)) {
            throw new \InvalidArgumentException('Unknown signal value for setting quantitative Antibody result');
        }

        $this->conclusionQuantitative = $number;

        if (isset($conclusion)) {
            $this->setConclusion($conclusion);
        }
    }

    public function getConclusionQuantitative(): ?int
    {
        return $this->conclusionQuantitative;
    }
}

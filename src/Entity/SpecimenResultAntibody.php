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
    const SIGNAL_NEGATIVE_TEXT = "NEGATIVE";
    const SIGNAL_NEGATIVE_NUMBER = "0";

    const SIGNAL_PARTIAL_TEXT = "PARTIAL";
    const SIGNAL_PARTIAL_NUMBER = "1";

    const SIGNAL_WEAK_TEXT = "WEAK";
    const SIGNAL_WEAK_NUMBER = "2";

    const SIGNAL_STRONG_TEXT = "STRONG";
    const SIGNAL_STRONG_NUMBER = "3";

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
     * @var null|string
     * @ORM\Column(name="signal_value", type="string", length=255, nullable=true)
     */
    private $signal;

    /**
     * @param string      $conclusion CONCLUSION_* constant
     * @param null|string $signal     SIGNAL_*_NUMBER value, called "Signal"
     */
    public function __construct(SpecimenWell $well, string $conclusion, ?string $signal = null)
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
        $this->setSignal($signal);
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
            'Non-Negative' => self::CONCLUSION_NON_NEGATIVE,
        ];
    }

    /**
     * @return string[]
     */
    public static function getFormSignal(): array
    {
        $validValues = range(self::SIGNAL_NEGATIVE_NUMBER, self::SIGNAL_STRONG_NUMBER);
        $validValues = array_map('strval', $validValues);

        return array_combine($validValues, $validValues);
    }

    public function setSignal(?string $signal)
    {
        $this->signal = $signal;
    }

    public function getSignal(): ?string
    {
        return $this->signal;
    }
}

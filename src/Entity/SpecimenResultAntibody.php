<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Result of analyzing presence of antibodies in Specimen.
 *
 * @ORM\Entity(repositoryClass="App\Repository\SpecimenResultAntibodyRepository")
 * NOTE: (a)ORM\Table defined on parent class
 */
class SpecimenResultAntibody extends SpecimenResult
{
    // When the specimen is rejected during assay.
    // This is only for import process. While importing, SpecimenResults with this status will not be created.
    const CONCLUSION_REJECTED = "REJECTED";

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
     * @ORM\ManyToOne(targetEntity="App\Entity\SpecimenWell", inversedBy="resultsAntibody", fetch="EAGER")
     * @ORM\JoinColumn(name="specimen_well_id", referencedColumnName="id")
     */
    private $well;

    /**
     * Specimen analyzed.
     *
     * @var Specimen
     * @ORM\ManyToOne(targetEntity="App\Entity\Specimen", inversedBy="resultsAntibody")
     * @ORM\JoinColumn(name="specimen_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $specimen;

    /**
     * Numerical representation of Conclusion.
     *
     * @var null|string
     * @ORM\Column(name="signal_value", type="string", length=255, nullable=true)
     * @Gedmo\Versioned
     */
    private $signal;

    /**
     * @param string      $conclusion CONCLUSION_* constant
     * @param null|string $signal     SIGNAL_*_NUMBER value, called "Signal"
     */
    public function __construct(SpecimenWell $well, string $conclusion, ?string $signal = null)
    {
        parent::__construct();

        $specimen = $well->getSpecimen();
        if (!$specimen) {
            throw new \InvalidArgumentException('SpecimenWell must have a Specimen to associate SpecimenResultAntibody');
        }

        if (!$specimen->willAllowAddingResults()) {
            throw new \RuntimeException('Specimen not in Status that allows adding Antibody Results');
        }

        $this->specimen = $specimen;
        $this->specimen->addAntibodyResult($this);

        // Setup relationship between SpecimenWell <==> SpecimenResultsAntibody
        $this->well = $well;
        $well->addResultAntibody($this);

        $this->setConclusion($conclusion);
        $this->setSignal($signal);
    }

    /**
     * Convert audit log field changes from internal format to human-readable format.
     *
     * Audit Logging tracks field/value changes using entity property names
     * and values like this:
     *
     *     [
     *         "status" => "RESULTS", // STATUS_RESULTS constant value
     *         "createdAt" => \DateTime(...),
     *     ]
     *
     * This method should convert the changes to human-readable values like this:
     *
     *     [
     *         "status" => "Results Available",
     *         "Created At" => \DateTime(...), // Frontend can custom print with ->format(...)
     *     ]
     *
     * @param array $changes Keys are internal entity propertyNames, Values are internal entity values
     * @return mixed[] Keys are human-readable field names, Values are human-readable values
     */
    public static function makeHumanReadableAuditLogFieldChanges(array $changes): array
    {
        $keyConverter = [
            // Entity.propertyNameHere => Human-Readable Description
            'conclusion' => 'Conclusion',
            'signal' => 'Signal',
            'webHookStatus' => 'Web Hook Status',
            'webHookStatusMessage' => 'Web Hook Status Message',
            'webHookLastTriedPublishingAt' => 'Web Hook Last Sent',
        ];

        /**
         * Keys are array key from $changes
         * Values are callbacks to convert $changes[$key] value
         */
        $valueConverter = [
            'webHookLastTriedPublishingAt' => function(?\DateTimeInterface $value) {
                return $value ? $value->format('Y-m-d g:ia') : null;
            },
            'conclusion' => function($value) {
                return $value ? self::lookupConclusionText($value) : '(empty)';
            },
        ];

        $return = [];
        foreach ($changes as $fieldId => $value) {
            // If mapping fieldId to human-readable string, use it
            // Else fallback to original fieldId
            $key = $keyConverter[$fieldId] ?? $fieldId;

            // If mapping callback defined for fieldId, use it
            // Else fallback to current value
            $value = isset($valueConverter[$fieldId]) ? $valueConverter[$fieldId]($value) : $value;

            $return[$key] = $value;
        }

        return $return;
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
        // Mark this record as needing to be sent to Web Hooks when Conclusion has changed
        if ($this->signal !== $signal) {
            $this->setWebHookQueued('Field published to web hooks changed');
        }

        $this->signal = $signal;
    }

    public function getSignal(): ?string
    {
        return $this->signal;
    }
}

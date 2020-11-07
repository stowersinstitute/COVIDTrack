<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

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
     * @ORM\ManyToOne(targetEntity="App\Entity\SpecimenWell", inversedBy="resultsQPCR", fetch="EAGER")
     * @ORM\JoinColumn(name="specimen_well_id", referencedColumnName="id")
     */
    private $well;

    /**
     * Specimen analyzed.
     *
     * @var Specimen
     * @ORM\ManyToOne(targetEntity="App\Entity\Specimen", inversedBy="resultsQPCR")
     * @ORM\JoinColumn(name="specimen_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $specimen;

    /**
     * Cycle Threshold (Ct) is a qPCR result metric for the number of cycles
     * required for the fluorescent signal to cross the threshold
     * (i.e. exceeding the background level).
     *
     * @var null|string
     * @ORM\Column(name="ct1", type="string", length=255, nullable=true)
     * @Gedmo\Versioned
     */
    private $ct1;

    /**
     * @var null|string
     * @ORM\Column(name="ct1_amp_score", type="string", length=255, nullable=true)
     * @Gedmo\Versioned
     */
    private $ct1AmpScore;

    /**
     * Cycle Threshold (Ct) is a qPCR result metric for the number of cycles
     * required for the fluorescent signal to cross the threshold
     * (i.e. exceeding the background level).
     *
     * @var null|string
     * @ORM\Column(name="ct2", type="string", length=255, nullable=true)
     * @Gedmo\Versioned
     */
    private $ct2;

    /**
     * @var null|string
     * @ORM\Column(name="ct2_amp_score", type="string", length=255, nullable=true)
     * @Gedmo\Versioned
     */
    private $ct2AmpScore;

    /**
     * Cycle Threshold (Ct) is a qPCR result metric for the number of cycles
     * required for the fluorescent signal to cross the threshold
     * (i.e. exceeding the background level).
     *
     * @var null|string
     * @ORM\Column(name="ct3", type="string", length=255, nullable=true)
     * @Gedmo\Versioned
     */
    private $ct3;

    /**
     * @var null|string
     * @ORM\Column(name="ct3_amp_score", type="string", length=255, nullable=true)
     * @Gedmo\Versioned
     */
    private $ct3AmpScore;

    /**
     * @param string $conclusion SpecimenResult::CONCLUSION_* constant
     */
    public function __construct(Specimen $specimen, string $conclusion)
    {
        parent::__construct();

        if (!$specimen->willAllowAddingResults()) {
            throw new \RuntimeException('Specimen not in Status that allows adding Viral Results');
        }

        $this->specimen = $specimen;
        $this->specimen->addQPCRResult($this);

        $this->setConclusion($conclusion);
    }

    /**
     * @param string       $conclusion SpecimenResultQPCR::CONCLUSION_* constant
     */
    public static function createFromWell(SpecimenWell $well, string $conclusion): self
    {
        if (!$well->getSpecimen()) {
            throw new \InvalidArgumentException('SpecimenWell must have a Specimen to associate SpecimenResultQPCR');
        }

        $r = new self($well->getSpecimen(), $conclusion);

        // Setup relationship between SpecimenWell <==> SpecimenResultsQPCR
        $r->well = $well;
        $well->addResultQPCR($r);

        return $r;
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
            'ct1' => 'Ct1',
            'ct1AmpScore' => 'Amp Score1',
            'ct2' => 'Ct2',
            'ct2AmpScore' => 'Amp Score2',
            'ct3' => 'Ct3',
            'ct3AmpScore' => 'Amp Score3',
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

   /**
     * @param string $text Text normally displayed in web form to select conclusions
     * @return string|null SpecimenResultQPCR::CONCLUSION_* constant, else NULL if not mapped to a conclusion
     */
    public static function lookupConclusionConstant(string $text): ?string
    {
        // Keys are human-readable text
        // Values are constant values
        $conclusions = self::getFormConclusions();

        return $conclusions[$text] ?? null;
    }

    public function getWell(): ?SpecimenWell
    {
        return $this->well;
    }

    public function getSpecimen(): Specimen
    {
        return $this->specimen;
    }

    public function getWellPlate(): ?WellPlate
    {
        return $this->well ? $this->well->getWellPlate() : null;
    }

    public function getWellPosition(): ?string
    {
        if (!$this->well) {
            return null;
        }

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
            'Not Detected' => self::CONCLUSION_NEGATIVE,
            'Inconclusive' => self::CONCLUSION_NON_NEGATIVE,
            'Recommended' => self::CONCLUSION_RECOMMENDED,
            'Detected' => self::CONCLUSION_POSITIVE,
        ];
    }

    public function getCT1(): ?string
    {
        return $this->ct1;
    }

    public function setCT1(?string $ct): void
    {
        $this->ct1 = $ct;
    }

    public function getCT1AmpScore(): ?string
    {
        return $this->ct1AmpScore;
    }

    public function setCT1AmpScore(?string $ampScore): void
    {
        $this->ct1AmpScore = $ampScore;
    }

    public function getCT2(): ?string
    {
        return $this->ct2;
    }

    public function setCT2(?string $ct): void
    {
        $this->ct2 = $ct;
    }

    public function getCT2AmpScore(): ?string
    {
        return $this->ct2AmpScore;
    }

    public function setCT2AmpScore(?string $ampScore): void
    {
        $this->ct2AmpScore = $ampScore;
    }

    public function getCT3(): ?string
    {
        return $this->ct3;
    }

    public function setCT3(?string $ct): void
    {
        $this->ct3 = $ct;
    }

    public function getCT3AmpScore(): ?string
    {
        return $this->ct3AmpScore;
    }

    public function setCT3AmpScore(?string $ampScore): void
    {
        $this->ct3AmpScore = $ampScore;
    }
}

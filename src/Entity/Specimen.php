<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Biological material collected from a study Participant.
 * Each Participant belongs to a Participant Group. The Specimen is associated
 * to the group instead of the participant to maintain some anonymity.
 *
 * @ORM\Entity(repositoryClass="App\Entity\SpecimenRepository")
 * @Gedmo\Loggable(logEntryClass="App\Entity\AuditLog")
 */
class Specimen
{
    use TimestampableEntity, SoftDeleteableEntity;

    const STATUS_CREATED = "CREATED";
    const STATUS_PENDING = "PENDING";
    const STATUS_IN_PROCESS = "IN_PROCESS";
    const STATUS_RESULTS = "RESULTS";
    const STATUS_COMPLETE = "COMPLETE";

    const TYPE_BLOOD = "BLOOD";
    const TYPE_BUCCAL = "BUCCAL";
    const TYPE_NASAL = "NASAL";
    const TYPE_SALIVA = "SALIVA";

    const CLIA_REC_PENDING = "PENDING";
    const CLIA_REC_RECOMMENDED = "RECOMMENDED";
    const CLIA_REC_NO = "NO";

    /**
     * @var int
     * @ORM\Id()
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * Unique public ID for referencing this specimen.
     *
     * @var string
     * @ORM\Column(name="accessionId", type="string", unique=true)
     * @Gedmo\Versioned
     */
    private $accessionId;

    /**
     * Saliva, Blood, etc. Uses TYPE_* constants.
     *
     * @var string
     * @ORM\Column(name="type", type="string", nullable=true)
     * @Gedmo\Versioned
     */
    private $type;

    /**
     * Participant offering this specimen belongs to this Participant Group.
     *
     * @var ParticipantGroup
     * @ORM\ManyToOne(targetEntity="App\Entity\ParticipantGroup", inversedBy="specimens")
     * @ORM\JoinColumn(name="participantGroupId", referencedColumnName="id")
     */
    private $participantGroup;

    /**
     * @var WellPlate
     * @ORM\ManyToOne(targetEntity="App\Entity\WellPlate", inversedBy="specimens")
     */
    private $wellPlate;

    /**
     * Time when collected or received.
     *
     * @var \DateTime
     * @ORM\Column(name="collectedAt", type="datetime", nullable=true)
     * @Gedmo\Versioned
     */
    private $collectedAt;

    /**
     * Whether test results yield a recommendation Specimen Participant Group
     * should undergo CLIA-based testing.
     *
     * @var string
     * @ORM\Column(name="cliaTestingRecommendation", type="string")
     * @Gedmo\Versioned
     */
    private $cliaTestingRecommendation;

    /**
     * @var string
     * @ORM\Column(type="string")
     * @Gedmo\Versioned
     */
    private $status;

    /**
     * Results of analyzing a Specimen.
     *
     * @var SpecimenResult[]|ArrayCollection
     * @ORM\OneToMany(targetEntity="App\Entity\SpecimenResult", mappedBy="specimen")
     * @ORM\OrderBy({"createdAt" = "DESC"})
     */
    private $results;

    public function __construct(string $accessionId, ParticipantGroup $group)
    {
        $this->accessionId = $accessionId;
        $this->participantGroup = $group;

        $this->status = self::STATUS_CREATED;
        $this->results = new ArrayCollection();
        $this->cliaTestingRecommendation = self::CLIA_REC_PENDING;
        $this->createdAt = new \DateTime();
    }

    public function __toString()
    {
        return $this->getAccessionId();
    }

    /**
     * Build for tests.
     */
    public static function buildExample(string $accessionId, ParticipantGroup $group = null): self
    {
        $group = $group ?: ParticipantGroup::buildExample('G100');

        return new static($accessionId, $group);
    }

    /**
     * Convert audit log field changes from internal format to human-readable format.
     *
     * Audit Logging tracks field/value changes using entity property names
     * and values like this:
     *
     *     [
     *         "status" => "IN_PROCESS", // STATUS_IN_PROCESS constant value
     *         "createdAt" => \DateTime(...),
     *     ]
     *
     * This method should convert the changes to human-readable values like this:
     *
     *     [
     *         "Status" => "In Process",
     *         "Created At" => \DateTime(...), // Frontend can custom print with ->format(...)
     *     ]
     *
     * @param array $changes Keys are internal entity propertyNames, Values are internal entity values
     * @return mixed[] Keys are human-readable field names, Values are human-readable values
     */
    public static function makeHumanReadableAuditLogFieldChanges(array $changes): array
    {
        $keyConverter = [
            // Specimen.propertyNameHere => Human-Readable Description
            'accessionId' => 'Accession ID',
            'collectedAt' => 'Collected At',
            'cliaTestingRecommendation' => 'CLIA Testing Recommended?',
            'status' => 'Status',
            'createdAt' => 'Created At',
        ];

        /**
         * Keys are array key from $changes
         * Values are callbacks to convert $changes[$key] value
         */
        $valueConverter = [
            // Convert CLIA_REC_* constants into human-readable text
            'cliaTestingRecommendation' => function($value) {
                return self::lookupCliaTestingRecommendationText($value);
            },
            // Convert STATUS_* constants into human-readable text
            'status' => function($value) {
                return self::lookupStatusText($value);
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

    public function getId(): int
    {
        return $this->id;
    }

    public function getAccessionId(): string
    {
        return $this->accessionId;
    }

    /**
     * Return Specimen::TYPE_* constant used.
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->ensureValidType($type);
        $this->type = $type;
    }

    /**
     * @return string[]
     */
    public static function getFormTypes(): array
    {
        return [
            'Blood' => self::TYPE_BLOOD,
            'Buccal' => self::TYPE_BUCCAL,
            'Nasal' => self::TYPE_NASAL,
            'Saliva' => self::TYPE_SALIVA,
        ];
    }

    /**
     * Get human-readable text of selected Type
     */
    public function getTypeText(): string
    {
        if ($this->type === null) {
            return '';
        }

        // Remove empty/null choice
        $types = array_filter(self::getFormTypes());

        // Key by TYPE_* constant
        $types = array_flip($types);

        return $types[$this->type];
    }

    public function getParticipantGroup(): ParticipantGroup
    {
        return $this->participantGroup;
    }

    public function setParticipantGroup(ParticipantGroup $group): void
    {
        $this->participantGroup = $group;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        if (!in_array($status, self::getFormStatuses())) {
            throw new \InvalidArgumentException('Invalid status');
        }

        $this->status = $status;
    }

    /**
     * @return string[]
     */
    public static function getFormStatuses(): array
    {
        return [
            'Created' => self::STATUS_CREATED,
            'Pending' => self::STATUS_PENDING,
            'In Process' => self::STATUS_IN_PROCESS,
            'Results' => self::STATUS_RESULTS,
            'Complete' => self::STATUS_COMPLETE,
        ];
    }

    public function getStatusText(): string
    {
        return self::lookupStatusText($this->status);
    }

    public static function lookupStatusText(string $statusConstant): string
    {
        $statuses = array_flip(static::getFormStatuses());

        return $statuses[$statusConstant];
    }

    public function getCliaTestingRecommendedText(): string
    {
        return self::lookupCliaTestingRecommendationText($this->cliaTestingRecommendation);
    }

    public static function lookupCliaTestingRecommendationText(string $rec): string
    {
        $map = [
            self::CLIA_REC_PENDING => 'Awaiting Results',
            self::CLIA_REC_RECOMMENDED => 'Yes',
            self::CLIA_REC_NO => 'No',
        ];

        return $map[$rec] ?? '';
    }

    public function getWellPlate(): ?WellPlate
    {
        return $this->wellPlate;
    }

    public function setWellPlate(?WellPlate $wellPlate): void
    {
        $this->wellPlate = $wellPlate;
    }

    public function getCollectedAt(): ?\DateTime
    {
        return $this->collectedAt ? clone $this->collectedAt : null;
    }

    public function setCollectedAt(?\DateTime $collectedAt): void
    {
        $this->collectedAt = $collectedAt ? clone $collectedAt : null;
    }

    private function ensureValidType(?string $type): void
    {
        // NULL is ok
        if ($type === null) return;

        $valid = array_values(self::getFormTypes());

        if (!in_array($type, $valid, true)) {
            throw new \InvalidArgumentException('Unknown Specimen type');
        }
    }

    /**
     * List of all Results collected on this Specimen.
     *
     * @return SpecimenResult[]
     */
    public function getResults(): array
    {
        return $this->results->getValues();
    }

    /**
     * @internal Call new SpecimenResults($specimen) to associate
     */
    public function addResult(SpecimenResult $result): void
    {
        // TODO: Add de-duplicating logic
        $this->results->add($result);

        $this->recalculateCliaTestingRecommendation();
    }

    /**
     * Get qPCR Results for this Specimen.
     *
     * @param int $limit Max number of results to return
     * @return SpecimenResultQPCR[]
     */
    public function getQPCRResults(int $limit = null): array
    {
        $results = $this->results
            ->filter(function(SpecimenResult $r) {
                return ($r instanceof SpecimenResultQPCR);
            })->getValues();

        // Sort most recent createdAt first
        uasort($results, function (SpecimenResult $a, SpecimenResult $b) {
            return ($a->getCreatedAt() > $b->getCreatedAt()) ? -1 : 1;
        });

        // Can return only X most recent
        return $limit ? array_slice($results, 0, $limit) : $results;
    }

    public function getMostRecentQPCRResult(): ?SpecimenResultQPCR
    {
        $results = $this->getQPCRResults(1);

        return array_shift($results);
    }

    /**
     * @return SpecimenResultDDPCR[]
     */
    public function getDDPCRResults(): array
    {
        // TODO: This needs to sort by createdAt with newest first
        return $this->results->filter(function(SpecimenResult $r) {
            return ($r instanceof SpecimenResultDDPCR);
        })->getValues();
    }

    /**
     * @return SpecimenResultSequencing[]
     */
    public function getSequencingResults(): array
    {
        // TODO: This needs to sort by createdAt with newest first
        return $this->results->filter(function(SpecimenResult $r) {
            return ($r instanceof SpecimenResultSequencing);
        })->getValues();
    }

    /**
     * Calculate CLIA testing recommendation given current state of Specimen.
     *
     * @return string CLIA_REC_* constant
     */
    public function recalculateCliaTestingRecommendation(): string
    {
        // Current recommendation
        $rec = $this->cliaTestingRecommendation;

        // Latest qPCR result
        $qpcr = $this->getMostRecentQPCRResult();

        // When qPCR result available
        if ($qpcr) {
            // Get the qPCR conclusion
            $result = $qpcr->getConclusion();

            // qPCR conclusion ==> CLIA Recommendation
            $map = [
                SpecimenResultQPCR::CONCLUSION_POSITIVE => self::CLIA_REC_RECOMMENDED,
                SpecimenResultQPCR::CONCLUSION_NEGATIVE => self::CLIA_REC_NO,
                SpecimenResultQPCR::CONCLUSION_INCONCLUSIVE => self::CLIA_REC_PENDING,
                SpecimenResultQPCR::CONCLUSION_PENDING => self::CLIA_REC_PENDING,
            ];

            // Use mapped recommendation value, else keep existing rec
            if ($result && isset($map[$result])) {
                $rec = $map[$result];
            }
        }

        // Update recommendation
        $this->cliaTestingRecommendation = $rec;

        // Caller given latest rec
        return $this->cliaTestingRecommendation;
    }
}

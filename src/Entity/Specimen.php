<?php

namespace App\Entity;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Util\EntityUtils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use App\Traits\SoftDeleteableEntity;
use App\Traits\TimestampableEntity;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Biological material collected from a study Participant.
 * Each Participant belongs to a Participant Group. The Specimen is associated
 * to the group instead of the participant to maintain some anonymity.
 *
 * @ORM\Entity(repositoryClass="App\Entity\SpecimenRepository")
 * @ORM\Table(name="specimens")
 * @Gedmo\Loggable(logEntryClass="App\Entity\AuditLog")
 */
class Specimen
{
    use TimestampableEntity, SoftDeleteableEntity;

    const STATUS_CREATED = "CREATED";
    const STATUS_RETURNED = "RETURNED";
    const STATUS_EXTERNAL = "EXTERNAL";
    /**
     * @deprecated Will be removed after all Specimen in Accepted status are moved to other statuses
     */
    const STATUS_ACCEPTED = "ACCEPTED";
    const STATUS_REJECTED = "REJECTED"; // Possible Final Status
    const STATUS_RESULTS = "RESULTS"; // Possible Final Status

    const TYPE_BLOOD = "BLOOD";
    const TYPE_SALIVA = "SALIVA";

    const CLIA_REC_PENDING = "PENDING";
    const CLIA_REC_YES = "YES";
    const CLIA_REC_NO = "NO";

    /**
     * @var int
     * @ORM\Id()
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * Unique public ID for referencing this specimen.
     *
     * @var string
     * @ORM\Column(name="accession_id", type="string", unique=true)
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
     * @ORM\JoinColumn(name="participant_group_id", referencedColumnName="id")
     */
    private $participantGroup;

    /**
     * Tube that contains this Specimen.
     *
     * @var Tube
     * @ORM\OneToOne(targetEntity="App\Entity\Tube", mappedBy="specimen", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    private $tube;

    /**
     * Wells where this Specimen is contained.
     *
     * @var SpecimenWell[]|ArrayCollection
     * @ORM\OneToMany(targetEntity="App\Entity\SpecimenWell", mappedBy="specimen", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    private $wells;

    /**
     * qPCR Results associated with this Specimen.
     *
     * @var SpecimenResultQPCR[]|ArrayCollection
     * @ORM\OneToMany(targetEntity="App\Entity\SpecimenResultQPCR", mappedBy="specimen", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"createdAt" = "DESC"})
     */
    private $resultsQPCR;

    /**
     * Antibody Results associated with this Specimen.
     *
     * @var SpecimenResultAntibody[]|ArrayCollection
     * @ORM\OneToMany(targetEntity="App\Entity\SpecimenResultAntibody", mappedBy="specimen", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"createdAt" = "DESC"})
     */
    private $resultsAntibody;

    /**
     * Date and Time when this Specimen was extracted (collected) from the Participant.
     * For example, when they spit in the tube or did a blood draw.
     * This value and Tube.collectedAt are the same.
     *
     * @var \DateTime
     * @ORM\Column(name="collected_at", type="datetime", nullable=true)
     * @Gedmo\Versioned
     */
    private $collectedAt;

    /**
     * Whether test results yield a recommendation Specimen Participant Group
     * should undergo CLIA-based testing.
     *
     * @var string
     * @ORM\Column(name="clia_testing_recommendation", type="string", nullable=true)
     * @Gedmo\Versioned
     */
    private $cliaTestingRecommendation;

    /**
     * @var string
     * @ORM\Column(name="status", type="string")
     * @Gedmo\Versioned
     */
    private $status;

    public function __construct(string $accessionId, ParticipantGroup $group, Tube $tube)
    {
        $this->accessionId = $accessionId;
        $this->participantGroup = $group;
        $this->tube = $tube;

        $this->status = self::STATUS_CREATED;
        $this->wells = new ArrayCollection();
        $this->resultsQPCR = new ArrayCollection();
        $this->resultsAntibody = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();

        $group->addSpecimen($this);
        $tube->setSpecimen($this);
    }

    /**
     * Create a new Specimen
     *
     * @param string|null $accessionId If given, this string will used as the accession ID instead of a generated one.
     */
    public static function createNew(ParticipantGroup $group, Tube $tube, SpecimenAccessionIdGenerator $gen, ?string $accessionId): self
    {
        if (!$accessionId) {
            $accessionId = $gen->generate();
        }

        return new static($accessionId, $group, $tube);
    }

    /**
     * Create a Specimen from contents in the given Tube.
     *
     * @return Specimen
     */
    public static function createFromTube(Tube $tube, SpecimenAccessionIdGenerator $gen): self
    {
        // Use Tube's Participant Group
        $group = $tube->getParticipantGroup();
        if (!$group) {
            throw new \RuntimeException('Cannot create Specimen from Tube without Tube Participant Group');
        }

        $specimenAccessionId = null;
        if ($tube->getTubeType() === Tube::TYPE_SALIVA) {
            $specimenAccessionId = $tube->getAccessionId();
        }

        // New Specimen
        $s = static::createNew($group, $tube, $gen, $specimenAccessionId);

        // Specimen Type
        // TODO: Convert Tube::TYPE_* to use Specimen::TYPE_*?
        $typeMap = [
            Tube::TYPE_BLOOD => Specimen::TYPE_BLOOD,
            Tube::TYPE_SALIVA => Specimen::TYPE_SALIVA,
        ];
        $tubeType = $tube->getTubeType();
        if (!isset($typeMap[$tubeType])) {
            throw new \RuntimeException('Tube type does not map to Specimen type');
        }
        $s->setType($typeMap[$tubeType]);

        $s->setCollectedAt($tube->getCollectedAt());
        return $s;
    }

    public function __toString()
    {
        return $this->getAccessionId();
    }

    /**
     * Build for tests.
     */
    public static function buildExample(string $accessionId, ?ParticipantGroup $group = null, ?Tube $tube = null): self
    {
        $group = $group ?: ParticipantGroup::buildExample('G100');

        $tube = $tube ?: new Tube('T100');
        $tube->setParticipantGroup($group);

        return new static($accessionId, $group, $tube);
    }

    /**
     * Example Specimen for automated tests. Created in workflow status ready to
     * add Viral or Antibody Results.
     */
    public static function buildExampleReadyForResults(string $accessionId, ?ParticipantGroup $group = null, ?Tube $tube = null): self
    {
        $s = static::buildExample($accessionId, $group, $tube);

        $s->setStatus(static::STATUS_EXTERNAL);

        return $s;
    }

    public static function buildExampleSaliva(string $accessionId, ParticipantGroup $group = null, ?Tube $tube = null): self
    {
        $specimen = static::buildExample($accessionId, $group, $tube);
        $specimen->setType(static::TYPE_SALIVA);

        return $specimen;
    }

    public static function buildExampleBlood(string $accessionId, ParticipantGroup $group = null, ?Tube $tube = null): self
    {
        $specimen = static::buildExample($accessionId, $group, $tube);
        $specimen->setType(static::TYPE_BLOOD);

        return $specimen;
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
            // Specimen.propertyNameHere => Human-Readable Description
            'accessionId' => 'Accession ID',
            'type' => 'Type',
            'collectedAt' => 'Collection Time',
            'cliaTestingRecommendation' => 'CLIA Testing Recommended?',
            'status' => 'Status',
            'createdAt' => 'Created At',
        ];

        /**
         * Keys are array key from $changes
         * Values are callbacks to convert $changes[$key] value
         */
        $valueConverter = [
            'type' => function($value) {
                return $value ? self::lookupTypeText($value) : null;
            },
            // Convert CLIA_REC_* constants into human-readable text
            'cliaTestingRecommendation' => function($value) {
                return self::lookupCliaTestingRecommendationText($value);
            },
            // Convert STATUS_* constants into human-readable text
            'status' => function($value) {
                return self::lookupStatusText($value);
            },
            'collectedAt' => function(?\DateTimeInterface $value) {
                return $value ? $value->format('Y-m-d g:ia') : null;
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

    public function getId(): ?int
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

        // Group might not be accepting Saliva Specimens
        if ($type === self::TYPE_SALIVA) {
            if (false === $this->getParticipantGroup()->acceptsSalivaSpecimens()) {
                throw new \RuntimeException("Specimen's Group not configured to accept Saliva Specimens");
            }
        }

        // Group might not be accepting Blood Specimens
        if ($type === self::TYPE_BLOOD) {
            if (false === $this->getParticipantGroup()->acceptsBloodSpecimens()) {
                throw new \RuntimeException("Specimen's Group not configured to accept Blood Specimens");
            }
        }

        $this->recalculateCliaTestingRecommendation();
    }

    /**
     * @return string[]
     */
    public static function getFormTypes(): array
    {
        return [
            'Blood' => self::TYPE_BLOOD,
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

    public static function lookupTypeText(string $typeConstant): string
    {
        $types = array_flip(static::getFormTypes());

        return $types[$typeConstant] ?? '';
    }

    public function getTube(): Tube
    {
        return $this->tube;
    }

    public function getTubeAccessionId(): ?string
    {
        return $this->tube->getAccessionId();
    }

    public function getParticipantGroup(): ParticipantGroup
    {
        return $this->participantGroup;
    }

    /**
     * @internal Only available so admins can use SpecimenForm to edit a Specimen's Group
     * @deprecated Not actually deprecated but reserved for only internal use
     */
    public function setParticipantGroup(ParticipantGroup $group): void
    {
        $this->participantGroup = $group;
        $this->tube->setParticipantGroup($group);
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

        if ($status === self::STATUS_REJECTED) {
            $this->recalculateCliaTestingRecommendation();
        } else if ($status === self::STATUS_RESULTS) {
            // Tube now has results
            $this->tube->markResultsAvailable();
        }
    }

    /**
     * Update current status after results have been added.
     * Should not change status if already in a final status
     * such as deleted.
     *
     * @return string Current status after latest update
     */
    public function updateStatusWhenResultsSet(): string
    {
        if ($this->isDeleted()) {
            // Do not change
            return $this->status;
        }

        if (self::STATUS_REJECTED === $this->status) {
            // Do not change
            return $this->status;
        }

        if (self::STATUS_RESULTS === $this->status) {
            // Do not change
            return $this->status;
        }

        $updateIfInStatus = [
            self::STATUS_CREATED,
            self::STATUS_RETURNED,
            self::STATUS_EXTERNAL,
        ];
        if (in_array($this->status, $updateIfInStatus)) {
            // Specimen now has results
            $this->setStatus(self::STATUS_RESULTS);
        }

        return $this->getStatus();
    }

    /**
     * @return string[]
     */
    public static function getFormStatuses(): array
    {
        return [
            'Created' => self::STATUS_CREATED,
            'Returned' => self::STATUS_RETURNED,
            'External Processing' => self::STATUS_EXTERNAL,
            'Accepted' => self::STATUS_ACCEPTED, // deprecated
            'Rejected' => self::STATUS_REJECTED,
            'Results Available' => self::STATUS_RESULTS,
        ];
    }

    public function getStatusText(): string
    {
        return self::lookupStatusText($this->status);
    }

    public static function lookupStatusText(string $statusConstant): string
    {
        $statuses = array_flip(static::getFormStatuses());

        // Hardcoded to support legacy statuses that appear in audit log
        // but are no longer supported
        $statuses['ACCEPTED'] = 'Accepted';

        if (!isset($statuses[$statusConstant])) {
            throw new \RuntimeException('Unsupported status constant value when rendering status text');
        }

        return $statuses[$statusConstant];
    }

    /**
     * @return string CLIA_REC_* constant
     */
    public function getCliaTestingRecommendation(): ?string
    {
        return $this->cliaTestingRecommendation;
    }

    public function getCliaTestingRecommendedText(): string
    {
        // NOTE: See $this->recalculateCliaTestingRecommendation() for $this->cliaTestingRecommendation
        return self::lookupCliaTestingRecommendationText($this->cliaTestingRecommendation);
    }

    /**
     * @param null|string $rec CLIA_REC_* constant
     * @return string
     */
    public static function lookupCliaTestingRecommendationText(?string $rec): string
    {
        $map = [
            self::CLIA_REC_PENDING => 'Awaiting Results',
            self::CLIA_REC_YES => 'Recommend Diagnostic Testing',
            self::CLIA_REC_NO => 'No Recommendation',
        ];

        return $map[$rec] ?? '';
    }

    /**
     * @internal Do not call directly. Instead use `new SpecimenWell($plate, $specimen, $position)`
     */
    public function addWell(SpecimenWell $well): void
    {
        foreach ($this->wells as $existingWell) {
            if ($existingWell->isSame($well)) {
                // Abort adding
                return;
            }
        }

        $this->wells->add($well);
    }

    /**
     * @return SpecimenWell[]
     */
    public function getWells(): array
    {
        return $this->wells->getValues();
    }

    /**
     * Whether Specimen is on the given Well Plate.
     */
    public function isOnWellPlate(WellPlate $plate): bool
    {
        foreach ($this->wells as $well) {
            if (EntityUtils::isSameEntity($plate, $well->getWellPlate())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get Well where Specimen stored on given WellPlate at given position.
     */
    public function getWellAtPosition(WellPlate $plate, string $position): ?SpecimenWell
    {
        $wells = $this->getWellsOnPlate($plate);
        foreach ($wells as $well) {
            if ($well->getPositionAlphanumeric() === $position) {
                return $well;
            }
        }

        return null;
    }

    /**
     * Get Wells where Specimen is stored on given WellPlate.
     *
     * @return SpecimenWell[]
     */
    public function getWellsOnPlate(WellPlate $plate): array
    {
        $found = [];
        foreach ($this->wells as $well) {
            if (EntityUtils::isSameEntity($plate, $well->getWellPlate())) {
                $found[] = $well;
            }
        }

        return $found;
    }

    /**
     * Get all Well Plates where this Specimen is contained.
     *
     * @return WellPlate[]
     */
    public function getWellPlates(): array
    {
        $plates = [];
        foreach ($this->wells as $well) {
            $plate = $well->getWellPlate();
            if ($plate) {
                // Indexed to make unique array
                $plates[$plate->getBarcode()] = $plate;
            }
        }

        return array_values($plates);
    }

    public function getCollectedAt(): ?\DateTimeInterface
    {
        return $this->collectedAt ? clone $this->collectedAt : null;
    }

    public function setCollectedAt(?\DateTimeInterface $collectedAt): void
    {
        $this->collectedAt = $collectedAt ? clone $collectedAt : null;
    }

    /**
     * Get Barcode of all Well Plates where this Specimen is stored.
     *
     * @return string[]
     */
    public function getRnaWellPlateBarcodes(): array
    {
        $barcodes = [];
        foreach ($this->wells as $well) {
            $code = $well->getWellPlateBarcode();
            if ($code) {
                $barcodes[] = $code;
            }
        }

        return array_unique($barcodes);
    }

    /**
     * Whether this Specimen is in the correct state to accept published
     * results.
     */
    public function willAllowAddingResults(): bool
    {
        $valid = [
            self::STATUS_EXTERNAL, // Returned from External Processing, but not formally checked-in
            self::STATUS_RETURNED, // Specimen has been returned in a Tube
            self::STATUS_RESULTS,  // Already has results, can add more than 1 result
        ];

        return in_array($this->status, $valid);
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
     * Add new qPCR Result for this Specimen.
     *
     * @internal Should only call from SpecimenResultQPCR::__construct()
     */
    public function addQPCRResult(SpecimenResultQPCR $result): void
    {
        foreach ($this->resultsQPCR as $existingResult) {
            if ($result === $existingResult) {
                return;
            }
        }

        $this->resultsQPCR->add($result);

        $this->updateStatusWhenResultsSet();
    }

    /**
     * Get qPCR Results for this Specimen.
     *
     * @param int $limit Max number of results to return
     * @return SpecimenResultQPCR[]
     */
    public function getQPCRResults(int $limit = null): array
    {
        $results = $this->resultsQPCR->getValues();

        // Sort most recent createdAt first
        uasort($results, function (SpecimenResultQPCR $a, SpecimenResultQPCR $b) {
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
     * Calculate CLIA testing recommendation given current state of Specimen.
     *
     * @return string CLIA_REC_* constant
     */
    public function recalculateCliaTestingRecommendation(): string
    {
        // Only Saliva specimens support CLIA recommendations
        if ($this->getType() !== self::TYPE_SALIVA) {
            $this->cliaTestingRecommendation = null;
            return '';
        }

        // Rejected Specimens will not end up with any results,
        // thus will never have a CLIA Recommendation
        if ($this->getStatus() === self::STATUS_REJECTED) {
            $this->cliaTestingRecommendation = null;
            return '';
        }

        // Current recommendation
        $rec = $this->cliaTestingRecommendation ?? self::CLIA_REC_PENDING;

        // Latest viral result
        $qpcr = $this->getMostRecentQPCRResult();

        // When result available
        if ($qpcr) {
            // Get the conclusion
            $result = $qpcr->getConclusion();

            // conclusion ==> CLIA Recommendation
            $map = [
                SpecimenResultQPCR::CONCLUSION_POSITIVE => self::CLIA_REC_YES,
                SpecimenResultQPCR::CONCLUSION_RECOMMENDED => self::CLIA_REC_YES,
                SpecimenResultQPCR::CONCLUSION_NEGATIVE => self::CLIA_REC_NO,
                SpecimenResultQPCR::CONCLUSION_NON_NEGATIVE => self::CLIA_REC_NO,
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

    /**
     * Add new Antibody Result for this Specimen.
     *
     * @internal Should only call from SpecimenResultAntibody::__construct()
     */
    public function addAntibodyResult(SpecimenResultAntibody $result): void
    {
        foreach ($this->resultsAntibody as $existingResult) {
            if ($result === $existingResult) {
                return;
            }
        }

        $this->resultsAntibody->add($result);

        $this->updateStatusWhenResultsSet();
    }

    /**
     * Get Antibody Results for this Specimen.
     *
     * @param int $limit Max number of results to return
     * @return SpecimenResultAntibody[]
     */
    public function getAntibodyResults(int $limit = null): array
    {
        $results = $this->resultsAntibody->getValues();

        // Sort most recent createdAt first
        uasort($results, function (SpecimenResultAntibody $a, SpecimenResultAntibody $b) {
            return ($a->getCreatedAt() > $b->getCreatedAt()) ? -1 : 1;
        });

        // Can return only X most recent
        return $limit ? array_slice($results, 0, $limit) : $results;
    }

    public function getMostRecentAntibodyResult(): ?SpecimenResultAntibody
    {
        $results = $this->getAntibodyResults(1);

        return array_shift($results);
    }
}

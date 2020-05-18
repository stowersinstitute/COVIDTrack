<?php

namespace App\Entity;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Traits\SoftDeleteableEntity;
use App\Traits\TimestampableEntity;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Physical container that holds a Specimen.
 *
 * @ORM\Entity(repositoryClass="App\Repository\TubeRepository")
 * @ORM\Table(name="tubes")
 * @ORM\HasLifecycleCallbacks
 * @Gedmo\Loggable(logEntryClass="App\Entity\AuditLog")
 */
class Tube
{
    use TimestampableEntity, SoftDeleteableEntity;

    /**
     * Text prefix added to numeric part of Accession ID
     */
    const ACCESSION_ID_PREFIX = 'T';

    const STATUS_CREATED = "CREATED";
    const STATUS_PRINTED = "PRINTED";
    const STATUS_AT_KIOSK = "KIOSK";
    const STATUS_RETURNED = "RETURNED";
    const STATUS_ACCEPTED = "ACCEPTED";
    const STATUS_REJECTED = "REJECTED";

    const TYPE_BLOOD = "BLOOD";
    const TYPE_SALIVA = "SALIVA";
    const TYPE_SWAB = "SWAB";

    const CHECKED_IN_ACCEPTED = "ACCEPTED";
    const CHECKED_IN_REJECTED = "REJECTED";

    /**
     * @var int
     * @ORM\Id()
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * Unique public ID for referencing. This is referred to as the "Tube ID"
     *
     * @var string
     * @ORM\Column(name="accession_id", type="string", unique=true, nullable=true)
     * @Gedmo\Versioned
     */
    private $accessionId;

    /**
     * Participant Group scanned when Tube was returned.
     *
     * @var ParticipantGroup
     * @ORM\ManyToOne(targetEntity="App\Entity\ParticipantGroup")
     * @ORM\JoinColumn(name="participant_group_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $participantGroup;

    /**
     * Specimen created as result of Tube being checked in.
     *
     * @var Specimen
     * @ORM\ManyToOne(targetEntity="App\Entity\Specimen", cascade={"persist"})
     * @ORM\JoinColumn(name="specimen_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $specimen;

    /**
     * Current status of tube.
     *
     * @var string
     * @ORM\Column(name="status", type="string")
     * @Gedmo\Versioned
     */
    private $status;

    /**
     * @var string
     * @ORM\Column(name="tube_type", type="string", nullable=true)
     * @Gedmo\Versioned
     */
    private $tubeType;

    /**
     * Describes the distribution kit given to the Participant. Normally this
     * is entered by a Technician when the Tubes are checked-in.
     *
     * @var null|string
     * @ORM\Column(name="kit_type", type="text", nullable=true)
     * @Gedmo\Versioned
     */
    private $kitType;

    /**
     * Date/Time when Tube was returned by the Participant.
     *
     * @var \DateTimeImmutable
     * @ORM\Column(name="returned_at", type="datetime_immutable", nullable=true)
     * @Gedmo\Versioned
     */
    private $returnedAt;

    /**
     * @var DropOff
     * @ORM\ManyToOne(targetEntity="App\Entity\DropOff", inversedBy="tubes")
     * @ORM\JoinColumn(name="drop_off_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $dropOff;

    /**
     * What Check-in Technician decided when observing Tube and Specimen
     * during check-in process.
     *
     * Values are self::CHECK_IN_* constants.
     *
     * @var string
     * @ORM\Column(name="check_in_decision", type="string", length=255, nullable=true)
     * @Gedmo\Versioned
     */
    private $checkInDecision;

    /**
     * Date/Time when Tube was processed for check-in by Check-in Technician.
     *
     * @var \DateTimeImmutable
     * @ORM\Column(name="checked_in_at", type="datetime_immutable", nullable=true)
     * @Gedmo\Versioned
     */
    private $checkedInAt;

    /**
     * Username of the Check-in Tech that processed this Tube during Check-In.
     *
     * NOTE: Username may not exist in the system, this is not a guaranteed AppUser association
     *
     * @var string
     * @ORM\Column(name="checked_in_by_username", type="string", nullable=true, length=255)
     * @Gedmo\Versioned
     */
    private $checkedInByUsername;

    /**
     * Date and Time when this Specimen was extracted (collected) from the Participant.
     * For example, when they spit in the tube or did a blood draw.
     *
     * @var \DateTimeImmutable
     * @ORM\Column(name="collected_at", type="datetime", nullable=true)
     * @Gedmo\Versioned
     */
    private $collectedAt;

    public function __construct(string $accessionId = null)
    {
        $this->accessionId = $accessionId;
        $this->status = self::STATUS_CREATED;
    }

    public function __toString()
    {
        return $this->getAccessionId();
    }

    /**
     * We consider the created-at time when it was printed. Under normal use
     * a Tube record is created when its Label is printed.
     */
    public function getPrintedAt(): ?\DateTimeInterface
    {
        return $this->createdAt ?: null;
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
            'status' => 'Status',
            'tubeType' => 'Type',
            'kitType' => 'Kit Type',
            'collectedAt' => 'Collected At',
            'returnedAt' => 'Returned At',
            'checkInDecision' => 'Check-in Decision',
            'checkedInAt' => 'Checked-in At',
            'checkedInByUsername' => 'Checked-in By',
        ];

        $dateTimeConvert = function(?\DateTimeInterface $value) {
            return $value ? $value->format('Y-m-d g:ia') : null;
        };

        /**
         * Keys are array key from $changes
         * Values are callbacks to convert $changes[$key] value
         */
        $valueConverter = [
            // Convert STATUS_* constants into human-readable text
            'status' => function($value) {
                return self::lookupStatusText($value);
            },
            'collectedAt' => $dateTimeConvert,
            'returnedAt' => $dateTimeConvert,
            'checkedInAt' => $dateTimeConvert,
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

    /**
     * Create the Accession ID for this Tube.
     *
     * @ORM\PostPersist
     * @internal
     */
    public function createAccessionId(LifecycleEventArgs $e): void
    {
        // Exit early if already exists
        if (null !== $this->accessionId) {
            return;
        }

        if (!$this->id) {
            throw new \RuntimeException('Not all data present to create Tube Accession ID');
        }

        // Accession ID based on database ID, which is guaranteed unique
        $newInt = $this->id;

        $maxIntLength = 8;
        if (strlen($newInt) > $maxIntLength) {
            throw new \RuntimeException('Tube Accession ID generation exceeded maximum value');
        }

        // Convert to string and re-pad with leading zeros
        // 1235 => "00001235"
        $padWithChar = '0';
        $newNumber = str_pad($newInt, $maxIntLength, $padWithChar, STR_PAD_LEFT);

        // Prepend prefix
        $this->accessionId = self::ACCESSION_ID_PREFIX . $newNumber;

        $e->getEntityManager()->flush();
    }

    public function getAccessionId(): ?string
    {
        return $this->accessionId;
    }


    public function getStatus(): string
    {
        return $this->status;
    }

    public function getTubeType(): ?string
    {
        return $this->tubeType;
    }

    public function setTubeType(?string $tubeType): void
    {
        $this->mustBeValidTubeType($tubeType);

        $this->tubeType = $tubeType;
    }

    /**
     * Get human-readable text of selected Type
     */
    public function getTypeText(): string
    {
        if ($this->tubeType === null) {
            return '';
        }

        $types = self::getValidTubeTypes();

        // Key by TYPE_* constant
        $types = array_flip($types);

        return $types[$this->tubeType];
    }

    /**
     * @return string[]
     */
    public static function getValidTubeTypes(): array
    {
        return [
            'Blood' => self::TYPE_BLOOD,
            'Swab' => self::TYPE_SWAB,
            'Saliva' => self::TYPE_SALIVA,
        ];
    }

    public function getKitType(): ?string
    {
        return $this->kitType;
    }

    public function setKitType(?string $kitType): void
    {
        $this->kitType = $kitType;
    }

    public function getParticipantGroup(): ?ParticipantGroup
    {
        return $this->participantGroup;
    }

    /**
     * Set the Participant Group scanned when returning this tube.
     */
    public function setParticipantGroup(?ParticipantGroup $group): void
    {
        $this->participantGroup = $group;
    }

    /**
     * Set when Participant returned Tube at a Kiosk
     */
    public function setReturnedAt(?\DateTimeImmutable $returnedAt): void
    {
        $this->returnedAt = $returnedAt;
    }

    public function getReturnedAt(): ?\DateTimeImmutable
    {
        return $this->returnedAt;
    }

    public function getDropOff(): ?DropOff
    {
        return $this->dropOff;
    }

    public function setDropOff(DropOff $dropOff)
    {
        $this->dropOff = $dropOff;
    }

    /**
     * Call when a Tube is being returned by a Participant at a Kiosk.
     *
     * @param SpecimenAccessionIdGenerator $gen
     * @param DropOff            $drop
     * @param ParticipantGroup   $group
     * @param string             $tubeType Tube::TYPE_* constant
     * @param \DateTimeInterface $collectedAt DateTime when Participant collected their Specimen
     */
    public function kioskDropoff(DropOff $drop, ParticipantGroup $group, string $tubeType, \DateTimeInterface $collectedAt): void
    {
        $this->dropOff = $drop;
        $drop->addTube($this);

        // User-entered data from kiosk
        $this->setParticipantGroup($group);
        $this->setTubeType($tubeType);
        $this->setCollectedAt($collectedAt);

        $this->setStatus(self::STATUS_AT_KIOSK);
    }

    /**
     * Call when the drop off is complete
     *
     * @param SpecimenAccessionIdGenerator $gen
     */
    public function kioskDropoffComplete(SpecimenAccessionIdGenerator $gen): void
    {
        $this->setStatus(self::STATUS_RETURNED);

        $returnedAt = new \DateTimeImmutable();
        $this->setReturnedAt($returnedAt);

        // Create Specimen
        $this->specimen = Specimen::createFromTube($this, $gen);
        $this->specimen->setStatus(Specimen::STATUS_RETURNED);
    }

    /**
     * Return Tube to state from before Drop Off began.
     */
    public function kioskDropoffCancel(): void
    {
        $this->setTubeType(null);
        $this->setCollectedAt(null);
        $this->setReturnedAt(null);
        $this->setParticipantGroup(null);
        $this->setStatus(self::STATUS_PRINTED);
    }

    /**
     * Whether this Tube is in the correct state to be processed for a check-in
     * by a Check-in Technician.
     */
    public function isReadyForCheckin(): bool
    {
        return $this->checkInDecision === null;
    }

    public function getCheckInDecision(): ?string
    {
        return $this->checkInDecision;
    }

    /**
     * @param string $decision self::CHECKED_IN_* constant
     */
    public function setCheckInDecision(string $decision)
    {
        $valid = [
            self::CHECKED_IN_ACCEPTED,
            self::CHECKED_IN_REJECTED,
        ];
        if (!in_array($decision, $valid)) {
            throw new \InvalidArgumentException('Invalid check-in decision');
        }

        $this->checkInDecision = $decision;
    }

    public function getCheckInDecisionText(): string
    {
        switch ($this->checkInDecision) {
            case self::CHECKED_IN_ACCEPTED:
                return 'Accepted';
            case self::CHECKED_IN_REJECTED:
                return 'Rejected';
            default:
                return '';
        }
    }

    /**
     * Set when Check-in Tech processed the returned Tube.
     */
    public function setCheckedInAt(?\DateTimeImmutable $checkedInAt): void
    {
        $this->checkedInAt = $checkedInAt;
    }

    public function getCheckedInAt(): ?\DateTimeImmutable
    {
        return $this->checkedInAt;
    }

    public function getCheckedInByUsername(): ?string
    {
        return $this->checkedInByUsername;
    }

    public function setCheckedInByUsername(?string $checkedInByUsername): void
    {
        $this->checkedInByUsername = $checkedInByUsername;
    }

    public function setSpecimen(Specimen $specimen): void
    {
        $this->specimen = $specimen;
    }

    public function getSpecimen(): ?Specimen
    {
        return $this->specimen;
    }

    public function getCollectedAt(): ?\DateTimeInterface
    {
        return $this->collectedAt;
    }

    public function setCollectedAt(?\DateTimeInterface $collectedAt): void
    {
        $this->collectedAt = $collectedAt;
    }

    /**
     * When a label is printed for this tube
     */
    public function markPrinted()
    {
        $this->setStatus(self::STATUS_PRINTED);
    }

    /**
     * When a Participant has returned this Tube with their Specimen inside.
     * @deprecated Use method kioskDropoff(), this will flip private
     */
    public function markReturned(\DateTimeImmutable $returnedAt = null)
    {
        if ($returnedAt === null) $returnedAt = new \DateTimeImmutable();

        $this->setStatus(self::STATUS_RETURNED);
        $this->setReturnedAt($returnedAt);
    }

    /**
     * Check-In Technician confirms the Tube and Specimen appear in acceptable
     * condition to perform further research.
     */
    public function markAccepted(string $checkedInBy, \DateTimeImmutable $checkedInAt = null): void
    {
        if ($checkedInAt === null) $checkedInAt = new \DateTimeImmutable();

        // Tube
        $this->setStatus(self::STATUS_ACCEPTED);
        $this->setCheckInDecision(self::CHECKED_IN_ACCEPTED);
        $this->setCheckedInAt($checkedInAt);
        $this->setCheckedInByUsername($checkedInBy);

        // Specimen
        $this->specimen->setStatus(Specimen::STATUS_ACCEPTED);
    }

    /**
     * Check-In Technician observes condition of the Tube or Specimen that
     * would compromise further research.
     *
     * Conditions like the Specimen has leaked out of the tube into the surrounding bag.
     */
    public function markRejected(string $checkedInBy, \DateTimeImmutable $checkedInAt = null): void
    {
        if ($checkedInAt === null) $checkedInAt = new \DateTimeImmutable();

        // Tube
        $this->setStatus(self::STATUS_REJECTED);
        $this->setCheckInDecision(self::CHECKED_IN_REJECTED);
        $this->setCheckedInAt($checkedInAt);
        $this->setCheckedInByUsername($checkedInBy);

        // Specimen
        $this->specimen->setStatus(Specimen::STATUS_REJECTED);
    }

    /**
     * @see markReturned() and other status methods
     */
    private function setStatus(string $status): void
    {
        if (!in_array($status, self::getValidStatuses())) {
            throw new \InvalidArgumentException('Invalid status');
        }

        $this->status = $status;
    }

    /**
     * @return string[]
     */
    private static function getValidStatuses(): array
    {
        return [
            'Created' => self::STATUS_CREATED,
            'Label Printed' => self::STATUS_PRINTED,
            'At Kiosk' => self::STATUS_AT_KIOSK,
            'Returned' => self::STATUS_RETURNED,
            'Accepted' => self::STATUS_ACCEPTED,
            'Rejected' => self::STATUS_REJECTED,
        ];
    }

    public function getStatusText(): string
    {
        return self::lookupStatusText($this->status);
    }

    public static function lookupStatusText(string $statusConstant): string
    {
        $statuses = array_flip(static::getValidStatuses());

        return $statuses[$statusConstant];
    }

    private function mustBeValidTubeType(?string $tubeType)
    {
        if ($tubeType === null) return;

        if (!in_array($tubeType, self::getValidTubeTypes())) {
            throw new \InvalidArgumentException('Invalid Tube Type');
        }
    }
}

<?php

namespace App\Entity;

use App\Util\EntityUtils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use App\Traits\SoftDeleteableEntity;
use App\Traits\TimestampableEntity;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Population of Participants being studied.
 *
 * @ORM\Entity(repositoryClass="App\Entity\ParticipantGroupRepository")
 * @ORM\Table(name="participant_groups")
 * @Gedmo\Loggable(logEntryClass="App\Entity\AuditLog")
 */
class ParticipantGroup
{
    public const MIN_PARTICIPANT_COUNT = 0;
    public const MAX_PARTICIPANT_COUNT = 65000;

    use TimestampableEntity, SoftDeleteableEntity;

    /**
     * @var int
     * @ORM\Id()
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * Unique public ID for referencing this group.
     *
     * @var string
     * @ORM\Column(name="accession_id", type="string")
     * @Gedmo\Versioned
     */
    private $accessionId;

    /**
     * ID sourced from an external system. Used for reconciling group identity.
     *
     * @var string|null
     * @ORM\Column(name="external_id", type="string", length=255, nullable=true)
     * @Gedmo\Versioned
     */
    private $externalId;

    /**
     * Human-readable title to identify this group. Used instead of accessionId
     * so participants don't need to remember a number.
     *
     * @var string
     * @ORM\Column(name="title", type="string", nullable=true)
     * @Gedmo\Versioned
     */
    private $title;

    /**
     * Number of Participants in this group.
     *
     * @var integer
     * @ORM\Column(name="participant_count", type="smallint", options={"unsigned":true}, nullable=false)
     * @Gedmo\Versioned
     */
    private $participantCount;

    /**
     * Specimens collected belonging to members of this group. Specimens are
     * not associated with individual Participants, only the group.
     *
     * @var Specimen[]|ArrayCollection
     * @ORM\OneToMany(targetEntity="App\Entity\Specimen", mappedBy="participantGroup")
     */
    private $specimens;

    /**
     * @var DropOffWindow[]
     *
     * @ORM\ManyToMany(targetEntity="DropOffWindow", mappedBy="participantGroups")
     */
    private $dropOffWindows;

    /**
     * @var boolean If true, the system expects specimens for this group
     *
     * @ORM\Column(name="is_active", type="boolean", nullable=true)
     * @Gedmo\Versioned
     */
    private $isActive;

    /**
     * @var boolean If true, group will be considered a control group and not generate notifications or impact scheduling.
     *
     * @ORM\Column(name="is_control", type="boolean", nullable=false, options={"default":0})
     * @Gedmo\Versioned
     */
    private $isControl;

    /**
     * When true, Viral and Antibody results for Group Participants will be
     * published to the Results Web Hook.
     *
     * @var bool
     * @ORM\Column(name="enabled_for_results_web_hooks", type="boolean", options={"default":1})
     * @Gedmo\Versioned
     */
    private $enabledForResultsWebHooks;

    public function __construct(string $accessionId, int $participantCount)
    {
        $this->accessionId = $accessionId;
        $this->setParticipantCount($participantCount);
        $this->specimens = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->isActive = true;
        $this->isControl = false;
        $this->enabledForResultsWebHooks = true;

        $this->dropOffWindows = new ArrayCollection();
    }

    /**
     * Build for tests.
     */
    public static function buildExample(string $accessionId, int $participantCount = 5): self
    {
        return new static($accessionId, $participantCount);
    }

    public function __toString()
    {
        $title = $this->getTitle();
        if ($title) {
            return $title;
        }

        return $this->accessionId;
    }

    /**
     * Convert audit log field changes from internal format to human-readable format.
     *
     * Audit Logging tracks field/value changes using entity property names
     * and values like this:
     *
     *     [
     *         "status" => "ACCEPTED", // STATUS_ACCEPTED constant value
     *         "createdAt" => \DateTime(...),
     *     ]
     *
     * This method should convert the changes to human-readable values like this:
     *
     *     [
     *         "Status" => "Accepted",
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
            'externalId' => 'External ID',
            'title' => 'Title',
            'participantCount' => 'Participants',
            'createdAt' => 'Created At',
            'isActive' => 'Is Active?',
            'isControl' => 'Is Control Group?',
            'enabledForResultsWebHooks' => 'Publish Results to Web Hooks?',
        ];

        $fnYesNoFromBoolean = function ($bool) {
            return $bool ? 'Yes' : 'No';
        };
        /**
         * Keys are array key from $changes
         * Values are callbacks to convert $changes[$key] value
         */
        $valueConverter = [
            'isActive' => $fnYesNoFromBoolean,
            'isControl' => $fnYesNoFromBoolean,
            'enabledForResultsWebHooks' => $fnYesNoFromBoolean,
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

    public function getDropOffWindowDebugString()
    {
        $strings = [];
        foreach ($this->dropOffWindows as $window) {
            $strings[] = sprintf(
                '[%s %s-%s]',
                $window->getStartsAt()->format('D'),
                $window->getStartsAt()->format('H:i'),
                $window->getEndsAt()->format('H:i')
            );
        }

        if (!$strings) return '-- None --';

        return join(' ', $strings);
    }

    /**
     * @return DropOffWindow[]
     */
    public function getDropOffWindows() : array
    {
        return $this->dropOffWindows->getValues();
    }

    public function addDropOffWindow(DropOffWindow $dropOffWindow)
    {
        if (!$this->isActive()) {
            throw new \RuntimeException('Cannot add DropOffWindow when Group is inactive');
        }

        if ($this->hasDropOffWindow($dropOffWindow)) return;

        $this->dropOffWindows->add($dropOffWindow);
        $dropOffWindow->addParticipantGroup($this);
    }

    public function hasDropOffWindow(DropOffWindow $dropOffWindow)
    {
        foreach ($this->dropOffWindows as $window) {
            if (EntityUtils::isSameEntity($window, $dropOffWindow)) return true;
        }

        return false;
    }

    public function removeDropOffWindow(DropOffWindow $window)
    {
        if (!$this->hasDropOffWindow($window)) return;

        foreach ($this->dropOffWindows as $currWindow) {
            if (EntityUtils::isSameEntity($currWindow, $window)) {
                $this->dropOffWindows->removeElement($currWindow);
                $currWindow->removeParticipantGroup($this);
            }
        }
    }

    public function clearDropOffWindows()
    {
        foreach ($this->dropOffWindows as $window) {
            $this->removeDropOffWindow($window);
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAccessionId(): string
    {
        return $this->accessionId;
    }

    public function getTitle(): string
    {
        return (string) $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getParticipantCount(): int
    {
        return $this->participantCount;
    }

    public function setParticipantCount(int $participantCount): void
    {
        $this->validateParticipantCount($participantCount);

        $this->participantCount = $participantCount;
    }

    /**
     * List of current Specimens.
     *
     * @return Specimen[]
     */
    public function getSpecimens(): array
    {
        return $this->specimens->getValues();
    }

    public function addSpecimen(Specimen $specimen): void
    {
        // TODO: Add de-duplicating logic
        $this->specimens->add($specimen);
    }

    /**
     * Ensure participantCount within acceptable range, else throw exception.
     */
    private function validateParticipantCount(int $count): void
    {
        $min = self::MIN_PARTICIPANT_COUNT;
        $max = self::MAX_PARTICIPANT_COUNT;

        if ($count <= $max && $count >= $min) {
            // Everything ok
            return;
        }

        throw new \OutOfBoundsException(sprintf('participantCount must be between %d and %d', $min, $max));
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;

        // Deactivating removes from schedule
        if ($isActive === false) {
            $this->clearDropOffWindows();
        }
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): void
    {
        $this->externalId = $externalId;
    }

    public function isControl(): bool
    {
        return $this->isControl;
    }

    public function setIsControl(bool $isControl): void
    {
        $this->isControl = $isControl;
    }

    public function isEnabledForResultsWebHooks(): bool
    {
        return $this->enabledForResultsWebHooks;
    }

    public function setEnabledForResultsWebHooks(bool $enabledForResultsWebHooks): void
    {
        $this->enabledForResultsWebHooks = $enabledForResultsWebHooks;
    }
}

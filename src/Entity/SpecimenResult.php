<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Traits\SoftDeleteableEntity;
use App\Traits\TimestampableEntity;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Result of analyzing a Specimen. Subclass and specify unique fields.
 *
 * @ORM\Entity
 * @ORM\Table(
 *     name="specimen_results",
 *     indexes={
 *         @ORM\Index(name="specimen_results_created_at_idx", columns={"created_at"}),
 *         @ORM\Index(name="specimen_results_conclusion_idx", columns={"conclusion"})
 *     },
 * )
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="discr", type="string")
 * @ORM\DiscriminatorMap({
 *     "qpcr" = "SpecimenResultQPCR",
 *     "antibody" = "SpecimenResultAntibody",
 * })
 * @Gedmo\Loggable(logEntryClass="App\Entity\AuditLog")
 */
abstract class SpecimenResult
{
    use TimestampableEntity, SoftDeleteableEntity;

    // When analysis did not find evidence of what it was searching for.
    const CONCLUSION_NEGATIVE = "NEGATIVE";

    // When analysis found evidence of what it was searching for.
    const CONCLUSION_POSITIVE = "POSITIVE";

    // When result is not negative, but not certainly positive.
    // May be because of a dirty sample, or questionable analysis, or similar.
    const CONCLUSION_NON_NEGATIVE = "NON-NEGATIVE";

    // SpecimenResult not yet ready to send. May still be gathering data.
    const WEBHOOK_STATUS_PENDING = "PENDING";

    // SpecimenResult is in queue ready to be sent next time webhook data sent.
    const WEBHOOK_STATUS_QUEUED = "QUEUED";

    // SpecimenResult was successfully sent through the webhook.
    const WEBHOOK_STATUS_SUCCESS = "SUCCESS";

    // SpecimenResult experienced errors when sending to the webhook.
    const WEBHOOK_STATUS_ERROR = "ERROR";

    // SpecimenResult will never be sent to webhook. May be an old result.
    const WEBHOOK_STATUS_NEVER_SEND = "NEVER_SEND";

    /**
     * @var int
     * @ORM\Id()
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * Whether this analysis result encountered a failure.
     *
     * @var bool
     * @ORM\Column(name="is_failure", type="boolean")
     */
    protected $isFailure = false;

    /**
     * Conclusion about the Specimen based on analyzing it.
     *
     * @var string
     * @ORM\Column(name="conclusion", type="string", length=255)
     */
    protected $conclusion;

    /**
     * Status of this record being sent through Web Hook system.
     * Acceptable values are self::WEBHOOK_STATUS_* constants.
     *
     * @var null|string
     * @ORM\Column(name="web_hook_status", type="string", nullable=true)
     * @Gedmo\Versioned
     */
    protected $webHookStatus;

    /**
     * Human-readable description explaining more about current Web Hook status.
     *
     * @var null|string
     * @ORM\Column(name="web_hook_status_message", type="text", nullable=true)
     * @Gedmo\Versioned
     */
    protected $webHookStatusMessage;

    /**
     * Timestamp when SpecimenResult last attempted published through Web Hook system.
     *
     * @var null|\DateTimeImmutable
     * @ORM\Column(name="web_hook_last_tried_publishing_at", type="datetime_immutable", nullable=true)
     * @Gedmo\Versioned
     */
    protected $webHookLastTriedPublishingAt;

    /**
     * Subclass should define its own annotations for how it maps to SpecimenWell,
     * and return SpecimenWell from it.
     */
    abstract public function getWell(): ?SpecimenWell;

    /**
     * Subclass should decide how to return the related Specimen,
     * usually through the SpecimenWell.
     *
     * For example:
     *
     *     return $this->getWell()->getSpecimen();
     */
    abstract public function getSpecimen(): Specimen;

    /**
     * Subclass should decide how to return the related WellPlate,
     * usually through the SpecimenWell.
     *
     * For example:
     *
     *     return $this->getWell()->getWellPlate();
     */
    abstract public function getWellPlate(): ?WellPlate;

    /**
     * Subclass should decide how to return the related Well Position,
     * usually through the SpecimenWell.
     *
     * For example:
     *
     *     return $this->getWell()->getPositionAlphanumeric();
     */
    abstract public function getWellPosition(): ?string;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->webHookStatus = self::WEBHOOK_STATUS_PENDING;
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
            // Entity.propertyNameHere => Human-Readable Description
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

    public function getConclusion(): string
    {
        return $this->conclusion;
    }

    public function setConclusion(string $conclusion): void
    {
        if (!static::isValidConclusion($conclusion)) {
            throw new \InvalidArgumentException('Cannot set invalid result Conclusion');
        }

        // Mark this record as needing to be sent to Web Hooks when Conclusion has changed
        if ($this->conclusion !== $conclusion) {
            $this->setWebHookQueued('Field published to web hooks changed');
        }

        $this->conclusion = $conclusion;
    }

    public static function isValidConclusion(string $conclusion): bool
    {
        return in_array($conclusion, static::getFormConclusions());
    }

    public function getConclusionText(): string
    {
        $conclusions = array_flip(static::getFormConclusions());

        return $conclusions[$this->getConclusion()] ?? '';
    }

    /**
     * Overwrite method to return Conclusions supported by each subclass.
     *
     * @return string[]
     */
    public static function getFormConclusions(): array
    {
        return [
            'Negative' => static::CONCLUSION_NEGATIVE,
            'Non-Negative' => static::CONCLUSION_NON_NEGATIVE,
            'Positive' => static::CONCLUSION_POSITIVE,
        ];
    }

    public function getReportedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSpecimenAccessionId(): string
    {
        return $this->getSpecimen()->getAccessionId();
    }

    /**
     * Timestamp when Participant spit into tube or had blood drawn.
     */
    public function getSpecimenCollectedAt(): ?\DateTimeInterface
    {
        return $this->getSpecimen()->getCollectedAt();
    }

    public function getWellPlateBarcode(): ?string
    {
        return $this->getWellPlate() ? $this->getWellPlate()->getBarcode() : null;
    }

    public function setIsFailure(bool $bool): void
    {
        $this->isFailure = $bool;
    }

    public function isFailure(): bool
    {
        return $this->isFailure;
    }

    /**
     * Set web hook status and message why it was set.
     *
     * @param string                  $status       SpecimenResult::WEBHOOK_STATUS_* constant.
     * @param string|null             $message
     */
    public function setWebHookStatus(string $status, string $message = ''): void
    {
        self::ensureValidWebHookStatus($status);

        $this->webHookStatus = $status;
        $this->webHookStatusMessage = $message;
    }

    /**
     * Does nothing when given value is valid, else throws an Exception.
     *
     * @param string $status SpecimenResult::WEBHOOK_STATUS_* constant value
     */
    public static function ensureValidWebHookStatus(string $status): void
    {
        $validStatuses = [
            self::WEBHOOK_STATUS_PENDING,
            self::WEBHOOK_STATUS_QUEUED,
            self::WEBHOOK_STATUS_SUCCESS,
            self::WEBHOOK_STATUS_ERROR,
            self::WEBHOOK_STATUS_NEVER_SEND,
        ];
        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException('Invalid Web Hook Status');
        }
    }

    /**
     * Mark as ready and queued to send to Web Hooks next time data is sent.
     */
    public function setWebHookQueued(string $message = '')
    {
        $this->setWebHookStatus(self::WEBHOOK_STATUS_QUEUED, $message);
    }

    /**
     * @param \DateTimeImmutable $successReceivedAt Timestamp when successfully sent to Web Hook.
     * @param string             $message
     */
    public function setWebHookSuccess(\DateTimeImmutable $successReceivedAt, string $message = '')
    {
        $this->setWebHookStatus(self::WEBHOOK_STATUS_SUCCESS, $message);
        $this->setWebHookLastTriedPublishingAt($successReceivedAt);
    }

    /**
     * Mark as having experienced an error when sending to Web Hooks.
     *
     * @param \DateTimeImmutable $errorReceivedAt Timestamp when experienced error sending to Web Hook.
     */
    public function setWebHookError(\DateTimeImmutable $errorReceivedAt, string $message = '')
    {
        $this->setWebHookStatus(self::WEBHOOK_STATUS_ERROR, $message);
        $this->setWebHookLastTriedPublishingAt($errorReceivedAt);
    }

    /**
     * Mark to never be sent to Web Hooks.
     */
    public function setWebHookNeverSend(string $message = '')
    {
        $this->setWebHookStatus(self::WEBHOOK_STATUS_NEVER_SEND, $message);
    }

    /**
     * @return string|null SpecimenResult::WEBHOOK_STATUS_* constant
     */
    public function getWebHookStatus(): ?string
    {
        return $this->webHookStatus;
    }

    public function getWebHookStatusMessage(): ?string
    {
        return $this->webHookStatusMessage;
    }

    public function setWebHookLastTriedPublishingAt(?\DateTimeImmutable $timestamp): void
    {
        $this->webHookLastTriedPublishingAt = $timestamp;
    }

    public function getWebHookLastTriedPublishingAt(): ?\DateTimeImmutable
    {
        return $this->webHookLastTriedPublishingAt;
    }
}

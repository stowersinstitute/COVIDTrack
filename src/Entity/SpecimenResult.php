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
     * Only certain fields are sent out in Web Hook Request data. If one of
     * these fields changes the whole Result record should be sent again as
     * an "update" to the Web Hook.
     *
     * Fields monitored are below in the Timestampable() "field" property.
     *
     * @see NewResultsWebHookRequest::getRequestData()
     * @var \DateTimeImmutable
     * @Gedmo\Timestampable(on="change", field={"id", "conclusion", "createdAt"})
     * @ORM\Column(name="web_hook_field_changed_at", type="datetime_immutable")
     * @deprecated
     */
    protected $webHookFieldChangedAt;

    /**
     * Status of this record being sent through Web Hook system.
     * Acceptable values are self::WEBHOOK_STATUS_* constants.
     *
     * @var null|string
     * @ORM\Column(name="web_hook_status", type="string", nullable=true)
     */
    protected $webHookStatus;

    /**
     * Human-readable description explaining more about current Web Hook status.
     *
     * @var null|string
     * @ORM\Column(name="web_hook_status_message", type="text", nullable=true)
     */
    protected $webHookStatusMessage;

    /**
     * Timestamp when SpecimenResult last attempted published through Web Hook system.
     *
     * @var null|\DateTimeImmutable
     * @ORM\Column(name="web_hook_last_tried_publishing_at", type="datetime_immutable", nullable=true)
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
        $this->webHookFieldChangedAt = new \DateTimeImmutable();
        $this->webHookStatus = self::WEBHOOK_STATUS_PENDING;
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
     * @deprecated Will be removed in favor of explicitly setting webHookStatus when fields change
     */
    public function getWebHookFieldChangedAt(): \DateTimeImmutable
    {
        return $this->webHookFieldChangedAt;
    }

    /**
     * @param string                  $status       SpecimenResult::WEBHOOK_STATUS_* constant.
     * @param string|null             $message
     */
    protected function setWebHookStatus(string $status, string $message = ''): void
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

        $this->webHookStatus = $status;
        $this->webHookStatusMessage = $message;
    }

    /**
     * Mark result as ready and queued to send to Web Hooks next time data is sent.
     */
    public function setWebHookQueued(string $message = '')
    {
        $this->webHookStatus = self::WEBHOOK_STATUS_QUEUED;
        $this->webHookStatusMessage = $message;
    }

    /**
     * @param \DateTimeImmutable $successReceivedAt Timestamp when successfully sent to Web Hook.
     * @param string             $message
     */
    public function setWebHookSuccess(\DateTimeImmutable $successReceivedAt, string $message = '')
    {
        $this->webHookStatus = self::WEBHOOK_STATUS_SUCCESS;
        $this->webHookStatusMessage = $message;
        $this->setWebHookLastTriedPublishingAt($successReceivedAt);
    }

    /**
     * Mark result as having experienced an error when sending to Web Hooks.
     *
     * @param \DateTimeImmutable $errorReceivedAt Timestamp when experienced error sending to Web Hook.
     */
    public function setWebHookError(\DateTimeImmutable $errorReceivedAt, string $message = '')
    {
        $this->webHookStatus = self::WEBHOOK_STATUS_ERROR;
        $this->webHookStatusMessage = $message;
        $this->setWebHookLastTriedPublishingAt($errorReceivedAt);
    }

    /**
     * Mark result to never be sent to Web Hooks.
     */
    public function setWebHookNeverSend(string $message = '')
    {
        $this->webHookStatus = self::WEBHOOK_STATUS_NEVER_SEND;
        $this->webHookStatusMessage = $message;
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

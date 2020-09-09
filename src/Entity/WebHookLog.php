<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Logs from Web Hook system.
 *
 * Logged to database because Web Hook system handles sensitive Results data.
 * That data appears in the log file. Keeping in database allows same data
 * privacy as all database data, instead of writing to disk which could be
 * read if file permissions are mis-configured.
 *
 * @see \App\Api\WebHook\Client\WebHookLogHandler for where these entities are created
 * @ORM\Entity
 * @ORM\Table(name="web_hook_logs")
 */
class WebHookLog
{
    public const CONTEXT_LIFECYCLE_ID_KEY = '_LIFECYCLE_ID';

    /**
     * @var int
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * ID to allow aligning multiple logs across a single Request / Response
     * lifecycle. Does not need to be unique in this table, but should be
     * reasonably unique within a small timeframe.
     *
     * @var null|string
     * @ORM\Column(name="lifecycle_id", type="string", length=17, nullable=true)
     */
    private $lifecycleId;

    /**
     * @var string
     * @ORM\Column(name="message", type="text")
     */
    private $message = '';

    /**
     * @var array
     * @ORM\Column(name="context", type="array")
     */
    private $context = [];

    /**
     * @var null|int
     * @ORM\Column(name="level", type="smallint")
     */
    private $level;

    /**
     * @var string
     * @ORM\Column(name="level_name", type="string", length=50)
     */
    private $levelName = '';

    /**
     * @var array
     * @ORM\Column(name="extra", type="array")
     */
    private $extra = [];

    /**
     * @var \DateTimeImmutable
     * @ORM\Column(name="created_at", type="datetime_immutable")
     */
    private $createdAt;

    public function __construct(?string $lifecycleId = null)
    {
        $this->lifecycleId = $lifecycleId;
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function buildFromMonologRecord(array $record): self
    {
        $context = $record['context'];
        $lifecycleId = $context[self::CONTEXT_LIFECYCLE_ID_KEY] ?? null;

        $log = new static($lifecycleId);
        $log->setMessage($record['message']);
        $log->setLevel($record['level'], $record['level_name']);
        $log->setExtra($record['extra']);
        $log->setContext($context);

        return $log;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLifecycleId(): ?string
    {
        return $this->lifecycleId;
    }

    public function setLifecycleId(?string $id): void
    {
        $this->lifecycleId = $id;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function setLevel(int $level, string $levelName): void
    {
        $this->level = $level;
        $this->levelName = $levelName;
    }

    public function getLevelName(): string
    {
        return $this->levelName;
    }

    public function getExtra(): array
    {
        return $this->extra;
    }

    public function setExtra(array $extra): void
    {
        $this->extra = $extra;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}

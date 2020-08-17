<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Logs from Web Hook system.
 *
 * @ORM\Entity
 * @ORM\Table(name="web_hook_logs")
 */
class WebHookLog
{
    /**
     * @var int
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

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

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function buildFromMonologRecord(array $record): self
    {
        $log = new static();
        $log->setMessage($record['message']);
        $log->setLevel($record['level'], $record['level_name']);
        $log->setExtra($record['extra']);
        $log->setContext($record['context']);

        return $log;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
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

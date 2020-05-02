<?php

namespace App\Entity;

use Gedmo\Loggable\Entity\LogEntry;

/**
 * Audit Log entry for a single operation.
 */
class AuditLog
{
    /**
     * @var LogEntry
     */
    private $log;

    public function __construct(LogEntry $log)
    {
        $this->log = $log;
    }

    /**
     * Create array of AuditLog records using Gedmo Loggable LogEntry
     *
     * @param LogEntry[] $logEntries
     * @return AuditLog[]
     */
    public static function createManyFromLogEntry(array $logEntries): array
    {
        $created = [];
        foreach ($logEntries as $log) {
            $created[] = new static($log);
        }

        return $created;
    }

    public function getFieldChanges(): array
    {
        $changes = $this->log->getData();

        // Try invoking static method on logged entity
        $class = $this->log->getObjectClass();
        $method = 'makeHumanReadableAuditLogFieldChanges';
        if (method_exists($class, $method)) {
            $changes = call_user_func([$class, $method], $changes);
        }

        return $changes;
    }

    public function getAction(): string
    {
        return (string) $this->log->getAction();
    }

    public function getUsername(): string
    {
        return (string) $this->log->getUsername();
    }

    public function getLoggedAt(): \DateTimeInterface
    {
        return $this->log->getLoggedAt();
    }
}

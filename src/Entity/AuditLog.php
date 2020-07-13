<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Loggable\Entity\LogEntry;

/**
 * Audit Log entry for a single operation performed on an entity with logging enabled.
 *
 * NOTE: (a)ORM\Table defined on parent class \Gedmo\Loggable\Entity\LogEntry
 * @ORM\Entity(repositoryClass="App\Entity\AuditLogRepository")
 */
class AuditLog extends LogEntry
{
    /**
     * Return human-readable fields and values for each entity property change
     * recorded in this log.
     *
     * For example:
     *
     *     [
     *         "Status" => "Accepted",
     *         "Created At" => \DateTime(...), // Frontend can custom print with ->format(...)
     *     ]
     *
     * @return array
     */
    public function getFieldChanges(): ?array
    {
        // By default use the raw property names and values
        $changes = $this->getData();

        // There may be no changed fields, for example when soft deleting
        if (!$changes) return [];

        // Try invoking static method on logged entity to convert to human-readable
        $class = $this->getObjectClass();
        $method = 'makeHumanReadableAuditLogFieldChanges';
        if (method_exists($class, $method)) {
            $changes = call_user_func([$class, $method], $changes);
        }

        return $changes;
    }
}

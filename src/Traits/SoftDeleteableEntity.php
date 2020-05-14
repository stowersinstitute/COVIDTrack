<?php

namespace App\Traits;

use Doctrine\ORM\Mapping as ORM;

/**
 * Apply to any entity that needs soft-deleting. For example, to keep a record
 * in the database but pretend it doesn't exist by flipping a flag.
 *
 * Implements our version of Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity
 *
 * Usage in an entity:
 *
 *     class WorkRequest {
 *         use SoftDeleteableEntity;
 *     }
 */
trait SoftDeleteableEntity
{
    /**
     * Timestamp when this entity was deleted.
     *
     * @var \DateTimeImmutable
     * @ORM\Column(name="deleted_at", type="datetime_immutable", nullable=true)
     */
    protected $deletedAt;

    public function setDeletedAt(?\DateTimeImmutable $deletedAt)
    {
        $this->deletedAt = $deletedAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    /**
     * Whether this entity has been soft-deleted.
     */
    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }
}

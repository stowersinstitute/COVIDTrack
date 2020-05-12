<?php

namespace App\Traits;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Apply to any entity that needs timestamps.
 * Implements our version of Gedmo\Timestampable\Traits\TimestampableEntity
 *
 * Usage in an entity:
 *
 *     class WorkRequest {
 *         use TimestampableEntity;
 *     }
 */
trait TimestampableEntity
{
    /**
     * @var \DateTimeImmutable
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="created_at", type="datetime_immutable")
     */
    protected $createdAt;

    /**
     * @var \DateTimeImmutable
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(name="updated_at", type="datetime_immutable")
     */
    protected $updatedAt;

    public function setCreatedAt(\DateTimeImmutable $createdAt)
    {
        $this->createdAt = $createdAt;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}

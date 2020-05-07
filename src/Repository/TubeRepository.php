<?php

namespace App\Repository;

use App\Entity\Tube;
use Doctrine\ORM\EntityRepository;

/**
 * Query for Tube entities.
 */
class TubeRepository extends EntityRepository
{
    /**
     * @param int|string $id Tube.id or Tube.accessionId
     */
    public function findOneByAnyId($id): ?Tube
    {
        if (is_int($id)) {
            // Using findOneBy() instead of find()
            // so Exception not thrown when not found.
            return $this->findOneBy([
                'id' => $id,
            ]);
        }

        return $this->findOneBy([
            'accessionId' => $id,
        ]);
    }
}

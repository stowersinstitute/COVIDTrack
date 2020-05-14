<?php

namespace App\Repository;

use App\Entity\Specimen;
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

    public function getReturnedCount() : int
    {
        return $this->createQueryBuilder('t')
            ->select('count(t.id)')
            ->where('t.status = :status')
            ->setParameter('status', Tube::STATUS_RETURNED)
            ->getQuery()->getSingleScalarResult();
    }

    /**
     * Tubes ready for Check-In by a Technician
     *
     * @return Tube[]
     */
    public function findReadyForCheckin(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->setParameter('status', Tube::STATUS_RETURNED)

            ->orderBy('t.accessionId')

            ->getQuery()
            ->execute();
    }
}

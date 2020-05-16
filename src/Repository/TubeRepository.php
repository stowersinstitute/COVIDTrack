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

    /**
     * Return highest number Tube Accession ID currently saved.
     */
    public function findMaxAccessionId(): ?string
    {
        /** @var Tube $tube */
        $tube = $this->findOneBy([], ['accessionId' => 'desc']);

        return $tube ? $tube->getAccessionId() : null;
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

    public function findSpecimenAccessionIdByTubeAccessionId(string $tubeAccessionId): ?string
    {
        $found = $this->createQueryBuilder('t')
            ->select('t.accessionId as tubeId, s.accessionId as specimenId')
            ->join('t.specimen', 's')
            ->where('t.accessionId = :tubeAccessionId')
            ->setParameter('tubeAccessionId', $tubeAccessionId)
            ->getQuery()
            ->execute();

        if (count($found) === 0) {
            return null;
        }
        if (count($found) > 1) {
            throw new \RuntimeException(sprintf('Found more than one Tube for Accession ID %s', $tubeAccessionId));
        }

        $record = array_shift($found);

        return $record['specimenId'];
    }
}

<?php

namespace App\Repository;

use App\Entity\Tube;
use Doctrine\ORM\EntityRepository;

/**
 * Query for Tube entities.
 */
class TubeRepository extends EntityRepository
{
    public function findOneByAccessionId(string $id): ?Tube
    {
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

    /**
     * Filter list of Tubes to display.
     *
     * @see TubeFilterForm
     * @return Tube[]
     */
    public function filterByFormData(array $data): array
    {
        $qb = $this->createQueryBuilder('t');

        if (isset($data['status'])) {
            $qb->andWhere('t.status = :f_status');
            $qb->setParameter('f_status', $data['status']);
        }

        $qb->addOrderBy('t.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Find records that need sent through Tube Web Hook API.
     *
     * @return Tube[]
     */
    public function findDueForExternalProcessingWebHook(): array
    {
        return $this->createQueryBuilder('t')
            // TODO: CVDLS-254 Update Tube fixtures so query does not need this
            ->join('t.participantGroup', 'pg')
            ->andWhere('(pg.externalId IS NOT NULL)')
            // TODO END

            ->andWhere('t.webHookStatus = :webHookStatus')
            ->setParameter('webHookStatus', Tube::WEBHOOK_STATUS_QUEUED)

            // Ordered to help humans find a specific Tube in the list
            ->orderBy('t.accessionId')

            ->getQuery()
            ->execute();
    }
}

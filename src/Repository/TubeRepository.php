<?php

namespace App\Repository;

use App\Entity\Tube;
use App\Util\DateUtils;
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

    /**
     * Find all Tube records without an associated Specimen. These Tubes have
     * had their barcode label printed or have not yet been returned at a Kiosk.
     *
     * @return Tube[]
     */
    public function findWithoutSpecimen(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.specimen IS NULL')
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

        // Tube Type
        if (isset($data['tubeType'])) {
            $qb->andWhere('t.tubeType = :f_tubeType');
            $qb->setParameter('f_tubeType', $data['tubeType']);
        }

        // Status
        if (isset($data['status'])) {
            $qb->andWhere('t.status = :f_status');
            $qb->setParameter('f_status', $data['status']);
        }

        // Created At
        if (isset($data['createdAt'])) {
            $qb->andWhere('t.createdAt BETWEEN :f_createdAt_lower AND :f_createdAt_upper');
            $qb->setParameter('f_createdAt_lower', DateUtils::dayFloor($data['createdAt']));
            $qb->setParameter('f_createdAt_upper', DateUtils::dayCeil($data['createdAt']));
        }

        // External Processing At
        if (isset($data['externalProcessingAt'])) {
            $qb->andWhere('t.externalProcessingAt BETWEEN :f_externalProcessingAt_lower AND :f_externalProcessingAt_upper');
            $qb->setParameter('f_externalProcessingAt_lower', DateUtils::dayFloor($data['externalProcessingAt']));
            $qb->setParameter('f_externalProcessingAt_upper', DateUtils::dayCeil($data['externalProcessingAt']));
        }

        // Web Hook Status
        if (isset($data['webHookStatus'])) {
            $qb->andWhere('t.webHookStatus = :f_webHookStatus');
            $qb->setParameter('f_webHookStatus', $data['webHookStatus']);
        }

        // Web Hook Last Tried Publishing At
        if (isset($data['webHookLastTriedPublishingAt'])) {
            $qb->andWhere('t.webHookLastTriedPublishingAt BETWEEN :f_webHookLastTriedPublishingAt_lower AND :f_webHookLastTriedPublishingAt_upper');
            $qb->setParameter('f_webHookLastTriedPublishingAt_lower', DateUtils::dayFloor($data['webHookLastTriedPublishingAt']));
            $qb->setParameter('f_webHookLastTriedPublishingAt_upper', DateUtils::dayCeil($data['webHookLastTriedPublishingAt']));
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

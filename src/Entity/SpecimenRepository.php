<?php

namespace App\Entity;

use App\Form\SpecimenFilterForm;
use App\Util\DateUtils;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;

/**
 * Query for Specimen entities.
 */
class SpecimenRepository extends EntityRepository
{
    /**
     * @param int|string $id Specimen.id or Specimen.accessionId
     */
    public function findOneByAnyId($id): ?Specimen
    {
        if (is_int($id)) {
            return $this->find($id);
        }

        return $this->findOneByAccessionId($id);
    }

    public function findOneByAccessionId(string $accessionId): ?Specimen
    {
        return $this->findOneBy([
            'accessionId' => $accessionId,
        ]);
    }

    /**
     * Filter list of Specimens to display.
     *
     * @see SpecimenFilterForm
     * @return Specimen[]
     */
    public function filterByFormData(array $data): array
    {
        $qb = $this->createQueryBuilder('s')
            // Pre-join and select data displayed on Specimens > List screen
            ->select('s, pGroup, well, tube')
            ->join('s.participantGroup', 'pGroup')
            ->leftJoin('s.wells', 'well')
            ->leftJoin('s.tube', 'tube')
            ->orderBy('s.collectedAt');

        // Participant Group
        if (isset($data['participantGroup'])) {
            $qb->andWhere('s.participantGroup = :f_participantGroup');
            $qb->setParameter('f_participantGroup', $data['participantGroup']);
        }

        // Type
        if (isset($data['type'])) {
            $qb->andWhere('s.type = :f_type');
            $qb->setParameter('f_type', $data['type']);
        }

        // Status
        if (isset($data['status'])) {
            $qb->andWhere('s.status = :f_status');
            $qb->setParameter('f_status', $data['status']);
        }

        // Collection Time
        if (isset($data['collectedAt'])) {
            $qb->andWhere('s.collectedAt BETWEEN :f_collectedAt_lower AND :f_collectedAt_upper');
            $qb->setParameter('f_collectedAt_lower', DateUtils::dayFloor($data['collectedAt']));
            $qb->setParameter('f_collectedAt_upper', DateUtils::dayCeil($data['collectedAt']));
        }

        // Well Plate
        if (isset($data['wellPlate'])) {
            $qb->andWhere('well.wellPlate = :f_wellPlate');
            $qb->setParameter('f_wellPlate', $data['wellPlate']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * List Specimens for displaying in list for Participant Group.
     *
     * @return Specimen[]
     */
    public function findForGroupList(ParticipantGroup $group): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.participantGroup = :participantGroup')
            ->setParameter('participantGroup', $group)
            ->orderBy('s.collectedAt', 'DESC')
            ->getQuery()
            ->execute();
    }

    /**
     * Find unique list of DateTimes for when Viral Results were uploaded for Specimens.
     *
     * @return \DateTime[]
     */
    public function findAvailableGroupViralResultDates(): array
    {
        $asName = 'resultDate';
        $results = $this->createResultsQB('s')
            ->join('s.wells', 'w')
            ->join('w.resultsQPCR', 'r')
            // Requires database value has exact same time,
            // we may want to round dates to regular intervals
            ->select('DISTINCT(r.createdAt) as '.$asName)
            ->orderBy('r.createdAt')
            ->getQuery()
            ->execute();

        // Doctrine returns values as strings due to DISTINCT() use above
        // Each value looks like "2020-05-16 10:26:27"
        $dateStringsWithTime = array_column($results, $asName);

        // Narrow to unique set by YYYY-MM-DD
        // Convert to DateTime
        /** @var |DateTime $return */
        $return = [];
        foreach ($dateStringsWithTime as $dateStringWithTime) {
            $dt = new \DateTime($dateStringWithTime);
            $dt->setTime(0, 0, 0, 0);

            // Keyed by YYYY-MM-DD to make unique by date
            $return[$dt->format('Y-m-d')] = $dt;
        }

        // Remove unique-making index
        return array_values($return);
    }

    /**
     * Find Specimens belonging to members of given Participation Group,
     * but only Specimen collected at a certain time.
     *
     * @return Specimen[]
     */
    public function findByGroupForCollectionPeriod(ParticipantGroup $group, \DateTimeInterface $collectedAt): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.participantGroup = :group')
            ->setParameter('group', $group)

            ->andWhere('s.collectedAt = :collectedAt')
            ->setParameter('collectedAt', $collectedAt, Type::DATETIME)

            ->getQuery()
            ->execute();
    }

    /**
     * Find Specimens belonging to members of given Participation Group,
     * but only those with Results reported on a specific date.
     * Only returns Specimens with Viral Results.
     *
     * @return Specimen[]
     */
    public function findByGroupForViralResultsPeriod(ParticipantGroup $group, \DateTimeInterface $resultedOnDate): array
    {
        list($range) = DateUtils::getDaysFromRange($resultedOnDate, $resultedOnDate);

        $start = $range['start'];
        $end = $range['end'];

        return $this->createQueryBuilder('s')
            ->join('s.wells', 'w')
            ->join('w.resultsQPCR', 'r')
            ->where('s.participantGroup = :group')
            ->setParameter('group', $group)

            ->andWhere('r.createdAt BETWEEN :resultedAtStart AND :resultedAtEnd')
            ->setParameter('resultedAtStart', $start, Type::DATETIME)
            ->setParameter('resultedAtEnd', $end, Type::DATETIME)

            ->getQuery()
            ->execute();
    }

    public function getPendingResultsCount() : int
    {
        return $this->createQueryBuilder('s')
            ->select('count(s.id)')
            ->join('s.participantGroup', 'participantGroup')
            ->join('s.tube', 'tube')

            // Specimen statuses where results can be added
            ->where('s.status IN (:status)')
            ->setParameter('status', [
                Specimen::STATUS_RETURNED,
                Specimen::STATUS_EXTERNAL,
            ])

            // Not in a control group
            ->andWhere('participantGroup.isControl = false')

            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * QueryBuilder to query Specimen ready for reporting results.
     *
     * Must use `andWhere()` for additional WHERE clauses.
     *
     * @param string $alias
     * @return \Doctrine\ORM\QueryBuilder
     */
    private function createResultsQB(string $alias)
    {
        return $this->createQueryBuilder($alias)
            ->where($alias.'.status = :statusResulted')
            ->setParameter('statusResulted', Specimen::STATUS_RESULTS);
    }
}

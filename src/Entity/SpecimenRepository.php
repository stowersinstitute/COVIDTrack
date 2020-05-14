<?php

namespace App\Entity;

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
     * Find Species.collectedAt DateTimes for which Specimens have available results.
     *
     * @return \DateTime[]
     */
    public function findAvailableGroupResultDates(): array
    {
        $asName = 'collectedAt';
        $results = $this->createResultsQB('s')
            // Requires database value has exact same time,
            // we may want to round dates to regular intervals
            ->select('DISTINCT(s.collectedAt) as '.$asName)
            ->andWhere('s.collectedAt IS NOT NULL')
            ->orderBy('s.collectedAt')
            ->getQuery()
            ->execute();

        // Doctrine returns values as strings due to DISTINCT() use above
        $dateStrings = array_column($results, $asName);

        return array_map(function(string $dateString) {
            return new \DateTime($dateString);
        }, $dateStrings);
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

    public function getInProcessCount() : int
    {
        return $this->createQueryBuilder('s')
            ->select('count(s.id)')
            ->where('s.status = :inProcessStatus')
            ->setParameter('inProcessStatus', Specimen::STATUS_IN_PROCESS)
            ->getQuery()->getSingleScalarResult();
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

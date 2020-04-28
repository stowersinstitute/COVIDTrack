<?php

namespace App\Entity;

use Doctrine\ORM\EntityRepository;

/**
 * Query for CollectionEvent entities.
 */
class CollectionEventRepository extends EntityRepository
{
    /**
     * Find all Collection Events where a specific Participant Group was sampled.
     *
     * @return CollectionEvent[]
     */
    public function findByParticipantGroup(ParticipantGroup $group): array
    {
        return $this->createQueryBuilder('e')
            ->join('e.specimens', 'sp')
            ->where('sp.participantGroup = :group')
            ->setParameter('group', $group)
            ->addOrderBy('e.collectedOn', 'DESC')
            ->getQuery()
            ->execute();
    }

    /**
     * Count number of Collection Events used by all Participant Groups.
     *
     * @return array Keys are ParticipantGroup.accessionId;
     *               Values are CollectionEvent count for that ParticipantGroup
     */
    public function findCountsIndexedByGroupAccessionId(): array
    {
        $results = $this->createQueryBuilder('e')
            ->select('count(DISTINCT e.id) as numGroups, pg.accessionId')
            ->join('e.specimens', 'sp')
            ->join('sp.participantGroup', 'pg')
            ->addGroupBy('pg.accessionId')
            ->getQuery()
            ->execute();

        $counts = [];
        foreach ($results as $result) {
            $id = $result['accessionId'];
            $counts[$id] = $result['numGroups'];
        }

        return $counts;
    }
}

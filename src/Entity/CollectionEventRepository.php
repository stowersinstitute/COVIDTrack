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
        $results = $this->getEntityManager()->createQuery("
            SELECT COUNT(DISTINCT event.id) as numGroups, pg.accessionId
            FROM App\Entity\CollectionEvent event
            JOIN event.specimens sp
            JOIN sp.participantGroup pg
            GROUP BY pg.accessionId
        ")
        ->execute();

        $counts = [];
        foreach ($results as $result) {
            $id = $result['accessionId'];
            $counts[$id] = $result['numGroups'];
        }

        return $counts;
    }
}

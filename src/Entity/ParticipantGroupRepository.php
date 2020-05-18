<?php

namespace App\Entity;

use Doctrine\ORM\EntityRepository;

/**
 * Query for ParticipantGroup entities.
 */
class ParticipantGroupRepository extends EntityRepository
{
    /**
     * @param int|string $id ParticipantGroup.id or ParticipantGroup.accessionId
     */
    public function findOneByAnyId($id): ?ParticipantGroup
    {
        if (is_int($id)) {
            return $this->find($id);
        }

        return $this->findOneBy([
            'title' => $id,
        ]);
    }

    /**
     * @return ParticipantGroup[]
     */
    public function findActive()
    {
        return $this->findBy(['isActive' => true], ['accessionId' => 'ASC']);
    }

    /**
     * @return ParticipantGroup[]
     */
    public function findActiveAlphabetical(): array
    {
        return $this->findBy(
            // Params
            [
                'isActive' => true,
            ],
            // Sort
            [
                'title' => 'ASC',
            ]
        );
    }

    /**
     * @param ParticipantGroup[] $groups
     * @return ParticipantGroup[]
     */
    public function findActiveNotIn(array $groups)
    {
        return $this->createQueryBuilder('g')
            ->where('
                g.isActive = true
                AND
                g.id NOT IN (:groups)
            ')
            ->setParameter('groups', $groups)
            ->getQuery()->getResult();
    }

    public function getActiveCount() : int
    {
        return $this->createQueryBuilder('g')
            ->select('count(g.id)')
            ->where('g.isActive = true')
            ->getQuery()->getSingleScalarResult();
    }
}

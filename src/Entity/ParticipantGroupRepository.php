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
            'accessionId' => $id,
        ]);
    }

    /**
     * @return ParticipantGroup[]
     */
    public function findActive()
    {
        // todo: implement isActive flag
        return $this->findBy([], ['accessionId' => 'ASC']);
    }
}

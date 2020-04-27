<?php

namespace App\Entity;

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

        return $this->findOneBy([
            'accessionId' => $id,
        ]);
    }
}

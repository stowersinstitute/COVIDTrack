<?php

namespace App\Repository;

use App\Entity\WellPlate;
use Doctrine\ORM\EntityRepository;

/**
 * Query for WellPlate entities.
 */
class WellPlateRepository extends EntityRepository
{
    /**
     * @param int|string $id WellPlate.id or WellPlate.barcode
     */
    public function findOneByAnyId($id): ?WellPlate
    {
        if (is_int($id)) {
            // Using findOneBy() instead of find()
            // so Exception not thrown when not found.
            return $this->findOneBy([
                'id' => $id,
            ]);
        }

        return $this->findOneByBarcode($id);
    }

    public function findOneByBarcode(string $barcode): ?WellPlate
    {
        return $this->findOneBy(['barcode' => $barcode]);
    }
}

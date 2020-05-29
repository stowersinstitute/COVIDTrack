<?php

namespace App\Repository;

use App\Entity\WellPlate;
use Doctrine\ORM\EntityRepository;

/**
 * Query for WellPlate entities.
 */
class WellPlateRepository extends EntityRepository
{
    public function findOneByBarcode(string $barcode): ?WellPlate
    {
        return $this->findOneBy(['barcode' => $barcode]);
    }
}

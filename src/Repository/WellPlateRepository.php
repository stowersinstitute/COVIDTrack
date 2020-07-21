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

    /**
     * Query used to list Well Plates in admin screen.
     *
     * @return WellPlate[]
     */
    public function findForListScreen(): array
    {
        return $this->createQueryBuilder('w')
            ->orderBy('w.createdAt', 'DESC')
            ->getQuery()
            ->execute();
    }
}

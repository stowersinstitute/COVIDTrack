<?php


namespace App\Entity;


use Doctrine\ORM\EntityRepository;

class KioskRepository extends EntityRepository
{
    /**
     * @return Kiosk[]
     */
    public function findUnprovisioned()
    {
        return $this->getDefaultQueryBuilder()
            ->where('k.isProvisioned = false')
            ->getQuery()->execute();
    }

    protected function getDefaultQueryBuilder()
    {
        return $this->createQueryBuilder('k')
            ->orderBy('k.label', 'ASC');
    }
}
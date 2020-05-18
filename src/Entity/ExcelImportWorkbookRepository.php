<?php


namespace App\Entity;


use Doctrine\ORM\EntityRepository;

class ExcelImportWorkbookRepository extends EntityRepository
{
    public function findExpired()
    {
        // "Expired" means anything older than this
        $cutoffTime = new \DateTimeImmutable('-7 days');

        return $this->createQueryBuilder('wb')
            ->where('wb.uploadedAt < :cutoffTime')
            ->setParameter('cutoffTime', $cutoffTime)
            ->getQuery()->getResult();
    }
}
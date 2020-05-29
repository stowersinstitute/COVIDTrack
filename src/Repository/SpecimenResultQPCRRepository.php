<?php

namespace App\Repository;

use App\Entity\SpecimenResultQPCR;
use Doctrine\ORM\EntityRepository;

/**
 * Query for SpecimenResultQPCR entities
 */
class SpecimenResultQPCRRepository extends EntityRepository
{

    /**
     * @return SpecimenResultQPCR[]
     */
    public function findPositiveGroups(\DateTimeInterface $datetime): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.createdAt > :since')
            ->setParameter('since', $datetime)
            ->orderBy('r.createdAt')
            ->getQuery()
            ->execute();
    }

    /**
     * Find Results whose conclusion recommends for testing,
     * and result was created after a certain time.
     *
     * @return SpecimenResultQPCR[]
     */
    public function findTestingRecommendedResultCreatedAfter(\DateTimeInterface $datetime): array
    {
        $conclusionRecommendingTesting = [
            SpecimenResultQPCR::CONCLUSION_RECOMMENDED,
            SpecimenResultQPCR::CONCLUSION_POSITIVE,
        ];

        return $this->createQueryBuilder('r')
            ->where('r.createdAt >= :since')
            ->setParameter('since', $datetime)

            ->andWhere('r.conclusion IN (:conclusions)')
            ->setParameter('conclusions', $conclusionRecommendingTesting)

            ->orderBy('r.createdAt')

            ->getQuery()
            ->execute();
    }
}

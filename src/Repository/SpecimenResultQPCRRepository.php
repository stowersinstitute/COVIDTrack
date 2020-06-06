<?php

namespace App\Repository;

use App\Entity\SpecimenResultQPCR;
use App\Form\SpecimenResultQPCRFilterForm;
use App\Util\DateUtils;
use Doctrine\ORM\EntityRepository;

/**
 * Query for SpecimenResultQPCR entities
 */
class SpecimenResultQPCRRepository extends EntityRepository
{
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

    /**
     * @see SpecimenResultQPCRFilterForm
     * @return SpecimenResultQPCR[]
     */
    public function filterByFormData($data): array
    {
        $qb = $this->createDefaultQueryBuilder('r');
        $qb->join('r.well', 'w')->addSelect('w');

        if (isset($data['conclusion'])) {
            $qb->andWhere('r.conclusion = :f_conclusion');
            $qb->setParameter('f_conclusion', $data['conclusion']);
        }

        if (isset($data['createdAtOn'])) {
            $qb->andWhere('r.createdAt BETWEEN :f_createdAtStart AND :f_createdAtEnd');
            $qb->setParameter('f_createdAtStart', DateUtils::dayFloor($data['createdAtOn']));
            $qb->setParameter('f_createdAtEnd', DateUtils::dayCeil($data['createdAtOn']));
        }

        return $qb->getQuery()->getResult();
    }

    protected function createDefaultQueryBuilder($alias = 'r')
    {
        return $this->createQueryBuilder($alias)
            ->orderBy($alias.'.createdAt', 'DESC')
            ->addOrderBy($alias.'.id', 'ASC')
        ;
    }
}

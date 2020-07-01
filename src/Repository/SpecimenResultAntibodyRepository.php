<?php

namespace App\Repository;

use App\Entity\SpecimenResultAntibody;
use App\Form\SpecimenResultAntibodyFilterForm;
use App\Util\DateUtils;
use Doctrine\ORM\EntityRepository;

/**
 * Query for SpecimenResultAntibody entities
 */
class SpecimenResultAntibodyRepository extends EntityRepository
{
    /**
     * Find Results whose conclusion recommends for testing,
     * and result was created after a certain time. This excludes
     * results from control group specimen.
     *
     * @return SpecimenResultAntibody[]
     */
    public function findTestingRecommendedResultCreatedAfter(\DateTimeInterface $datetime): array
    {
        throw new \RuntimeException('findTestingRecommendedResultCreatedAfter() Not yet supported');
        $conclusionRecommendingTesting = [
            SpecimenResultAntibody::CONCLUSION_RECOMMENDED,
            SpecimenResultAntibody::CONCLUSION_POSITIVE,
        ];

        return $this->createQueryBuilder('r')
            ->where('r.createdAt >= :since')
            ->setParameter('since', $datetime)

            // Do not include results from "control" groups
            ->join('r.well', 'w')
            ->join('w.specimen', 's')
            ->join('s.participantGroup', 'g')
            ->andWhere('g.isControl = false')

            ->andWhere('r.conclusion IN (:conclusions)')
            ->setParameter('conclusions', $conclusionRecommendingTesting)

            ->orderBy('r.createdAt')

            ->getQuery()
            ->execute();
    }

    /**
     * @see SpecimenResultAntibodyFilterForm
     * @return SpecimenResultAntibody[]
     */
    public function filterByFormData($data): array
    {
        throw new \RuntimeException('filterByFormData() Not yet supported');
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

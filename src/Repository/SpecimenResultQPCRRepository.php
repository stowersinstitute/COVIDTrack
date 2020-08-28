<?php

namespace App\Repository;

use App\Entity\SpecimenResultQPCR;
use App\Form\SpecimenResultQPCRFilterForm;
use App\Util\DateUtils;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;

/**
 * Query for SpecimenResultQPCR entities
 */
class SpecimenResultQPCRRepository extends EntityRepository
{
    /**
     * Find Results whose conclusion recommends for testing,
     * and result was last updated after a certain time. This excludes
     * results from control group specimen.
     *
     * @return SpecimenResultQPCR[]
     */
    public function findTestingRecommendedResultUpdatedAfter(\DateTimeInterface $datetime): array
    {
        $conclusionRecommendingTesting = [
            SpecimenResultQPCR::CONCLUSION_RECOMMENDED,
            SpecimenResultQPCR::CONCLUSION_POSITIVE,
        ];

        return $this->createQueryBuilder('r')
            ->where('r.updatedAt >= :since')
            ->setParameter('since', $datetime, Type::DATETIME)

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
     * Find Results whose conclusion was reported Non-Negative that were
     * last updated after given time.
     * This excludes results from Control Participant Groups.
     *
     * @return SpecimenResultQPCR[]
     */
    public function findTestingResultNonNegativeUpdatedAfter(\DateTimeInterface $datetime): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.updatedAt >= :since')
            ->setParameter('since', $datetime, Type::DATETIME)

            // Do not include results from "control" groups
            ->join('r.well', 'w')
            ->join('w.specimen', 's')
            ->join('s.participantGroup', 'g')
            ->andWhere('g.isControl = false')

            // Only Non-Negative Results
            ->andWhere('r.conclusion = :conclusion')
            ->setParameter('conclusion', SpecimenResultQPCR::CONCLUSION_NON_NEGATIVE)

            ->orderBy('r.createdAt')

            ->getQuery()
            ->execute();
    }

    /**
     * Find results that need sent to Viral Results Web Hook.
     *
     * @param \DateTimeInterface $since
     * @return SpecimenResultQPCR[]
     */
    public function findDueForWebHook(): array
    {
        return $this->createQueryBuilder('r')
            // JOINs to query based on Group
            ->join('r.specimen', 's')
            ->join('s.participantGroup', 'g')

            // Results that haven't been reported
            // OR updated since last successful web hook success
            ->where('(r.lastWebHookSuccessAt IS NULL OR r.webHookFieldChangedAt > r.lastWebHookSuccessAt)')

            // Only Active groups
            ->andWhere('g.isActive = true')

            // Only groups marked for publishing Viral results to Web Hooks
            ->andWhere('g.viralResultsWebHooksEnabled = true')

            ->orderBy('r.updatedAt')
            ->getQuery()
            ->execute();
    }

    /**
     * @see SpecimenResultQPCRFilterForm
     * @return SpecimenResultQPCR[]
     */
    public function filterByFormData(array $data): array
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

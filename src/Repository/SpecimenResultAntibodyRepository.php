<?php

namespace App\Repository;

use App\Entity\SpecimenResult;
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
     * Find Results whose conclusion is not Negative and where it was
     * modified after a certain time. This excludes results from Specimens
     * associated with Control Participant Groups.
     *
     * @return SpecimenResultAntibody[]
     */
    public function findAnyResultNotNegativeAfter(\DateTimeInterface $datetime): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.updatedAt >= :since')
            ->setParameter('since', $datetime)

            // Do not include results from "control" groups
            ->join('r.well', 'w')
            ->join('w.specimen', 's')
            ->join('s.participantGroup', 'g')
            ->andWhere('g.isControl = false')

            // Any result that is not negative
            ->andWhere('r.conclusion IS NOT NULL AND r.conclusion != :conclusion')
            ->setParameter('conclusion', SpecimenResultAntibody::CONCLUSION_NEGATIVE)

            ->orderBy('r.updatedAt')

            ->getQuery()
            ->execute();
    }

    /**
     * Find results that need sent to Antibody Results Web Hook.
     *
     * @return SpecimenResultAntibody[]
     */
    public function findDueForWebHook(): array
    {
        return $this->createQueryBuilder('r')
            // JOINs to query based on Group
            ->join('r.specimen', 's')
            ->join('s.participantGroup', 'g')

            // Only Active groups
            ->andWhere('g.isActive = true')

            // Only groups marked for publishing Antibody results to Web Hooks
            ->andWhere('g.antibodyResultsWebHooksEnabled = true')

            // Results queued to be sent
            ->andWhere('(r.webHookStatus = :webHookStatus)')
            ->setParameter('webHookStatus', SpecimenResult::WEBHOOK_STATUS_QUEUED)

            ->orderBy('r.updatedAt')
            ->getQuery()
            ->execute();
    }

    /**
     * @see SpecimenResultAntibodyFilterForm
     * @return SpecimenResultAntibody[]
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

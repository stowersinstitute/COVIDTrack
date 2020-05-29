<?php

namespace App\Repository;

use App\Entity\ParticipantGroup;
use App\Entity\StudyCoordinatorNotification;
use App\Util\DateUtils;
use Doctrine\ORM\EntityRepository;

/**
 * Query for StudyCoordinatorNotification entities
 */
class StudyCoordinatorNotificationRepository extends EntityRepository
{
    /**
     * Find list of Participant Groups that the Study Coordinator was
     * notified about on a specific date.
     *
     * @param \DateTime $date Date when Notification was created
     * @return ParticipantGroup[]
     */
    public function getGroupsNotifiedOnDate(\DateTime $date): array
    {
        /** @var StudyCoordinatorNotification[] $notifications */
        $notifications = $this->createQueryBuilder('n')
            ->select('n, g')
            ->join('n.recommendedGroups', 'g')
            ->where('n.createdAt BETWEEN :startTime AND :endTime')
            ->setParameter('startTime', DateUtils::dayFloor($date))
            ->setParameter('endTime', DateUtils::dayCeil($date))
            ->getQuery()
            ->execute();

        $groups = [];
        foreach ($notifications as $n) {
            foreach ($n->getRecommendedGroups() as $group) {
                $groups[$group->getId()] = $group;
            }
        }

        return $groups;
    }
}

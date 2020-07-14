<?php

namespace App\Repository;

use App\Entity\ParticipantGroup;
use App\Entity\EmailNotification;
use App\Util\DateUtils;
use Doctrine\ORM\EntityRepository;

/**
 * Query for EmailNotification entities
 */
class EmailNotificationRepository extends EntityRepository
{
    /**
     * Get the most recently sent notifications.
     *
     * @param int $limit
     * @return EmailNotification[]
     */
    public function findMostRecent(int $limit = 100): array
    {
        return $this->createQueryBuilder('n')
            ->addOrderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }

    /**
     * Get the most recent timestamp when a Notification was last sent.
     */
    public function getMostRecentSentAt(): ?\DateTimeImmutable
    {
        $mostRecent = $this->findOneBy([], ['createdAt' => 'DESC']);
        if (!$mostRecent) {
            return null;
        }

        return $mostRecent->getCreatedAt();
    }

    /**
     * Find list of Participant Groups that were included in a Notification
     * on given date.
     *
     * @param \DateTime $date Date when Notification was created
     * @return ParticipantGroup[]
     */
    public function getGroupsNotifiedOnDate(\DateTime $date): array
    {
        /** @var EmailNotification[] $notifications */
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

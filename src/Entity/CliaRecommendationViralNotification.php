<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Log of notifications sent to Study Coordinator, notifying them about
 * Participant Groups that are recommended for CLIA testing.
 *
 * @ORM\Entity(repositoryClass="App\Repository\EmailNotificationRepository")
 */
class CliaRecommendationViralNotification extends EmailNotification
{
}

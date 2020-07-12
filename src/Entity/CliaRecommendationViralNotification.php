<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Log of notifications sent notifying privileged users about
 * Participant Groups that are recommended for additional CLIA testing.
 *
 * @ORM\Entity(repositoryClass="App\Repository\EmailNotificationRepository")
 */
class CliaRecommendationViralNotification extends EmailNotification
{
}

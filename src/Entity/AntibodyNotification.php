<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Log of notifications about Antibody results. Sent to privileged users
 * notifying them about Participant Groups where at least one Specimen
 * returned any result other than Negative.
 *
 * @ORM\Entity(repositoryClass="App\Repository\EmailNotificationRepository")
 */
class AntibodyNotification extends EmailNotification
{
}

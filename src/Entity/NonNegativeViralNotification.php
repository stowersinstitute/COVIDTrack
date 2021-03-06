<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Log of Non-Negative notifications sent to privileged users, notifying them about
 * Participant Groups where at least one Specimen was "Non-Negative". The
 * "Non-Negative" does not mean "anything but Negative" and is instead an
 * explicit status.
 *
 * @ORM\Entity(repositoryClass="App\Repository\EmailNotificationRepository")
 */
class NonNegativeViralNotification extends EmailNotification
{
}

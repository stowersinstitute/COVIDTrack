<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Log of Non-Negative notifications sent to Study Coordinator, notifying them about
 * Participant Groups where at least one Specimen was "Non-Negative". The
 * "Non-Negative" does not mean "anything but Negative" and is instead an
 * explicit status.
 *
 * @ORM\Entity(repositoryClass="App\Repository\StudyCoordinatorNotificationRepository")
 */
class NonNegativeViralNotification extends StudyCoordinatorNotification
{
}

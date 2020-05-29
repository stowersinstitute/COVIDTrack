<?php

namespace App\Entity;

use App\Util\EntityUtils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use App\Traits\TimestampableEntity;
use Symfony\Component\Mime\Email;

/**
 * Log of notifications sent to Study Coordinator, notifying them about
 * Participant Groups that are recommended for testing.
 *
 * @ORM\Entity(repositoryClass="App\Repository\StudyCoordinatorNotificationRepository")
 * @ORM\Table(name="study_coordinator_notifications")
 */
class StudyCoordinatorNotification
{
    use TimestampableEntity;

    /**
     * @var int
     * @ORM\Id()
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * Participant Groups that were recommended for testing by this notification.
     *
     * @ORM\ManyToMany(targetEntity="App\Entity\ParticipantGroup")
     * @ORM\JoinTable(name="study_coordinator_notification_recommended_groups",
     *     joinColumns={
     *         @ORM\JoinColumn(name="notification_id", referencedColumnName="id")
     *     },
     *     inverseJoinColumns={
     *         @ORM\JoinColumn(name="participant_group_id", referencedColumnName="id")
     *     }
     * )
     */
    private $recommendedGroups;

    /**
     * @var string|null
     * @ORM\Column(name="from", type="text", nullable=true)
     */
    private $from;

    /**
     * @var string|null
     * @ORM\Column(name="recipients", type="text", nullable=true)
     */
    private $recipients;

    /**
     * @var string|null
     * @ORM\Column(name="subject", type="text", nullable=true)
     */
    private $subject;

    /**
     * @var string|null
     * @ORM\Column(name="message", type="text", nullable=true)
     */
    private $message;

    public function __construct()
    {
        $this->recommendedGroups = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * @param ParticipantGroup[] $groups
     */
    public static function createFromEmail(Email $email, array $groups): self
    {
        $from = [];
        foreach ($email->getFrom() as $address) {
            $from[] = $address->toString();
        }

        $recipients = [];
        foreach ($email->getTo() as $address) {
            $recipients[] = $address->toString();
        }

        $n = new static();
        $n->setFrom(implode(', ', $from));
        $n->setRecipients(implode(', ', $recipients));
        $n->setSubject($email->getSubject());
        $n->setMessage($email->getHtmlBody());

        foreach ($groups as $group) {
            $n->addRecommendedGroup($group);
        }

        return $n;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function addRecommendedGroup(ParticipantGroup $group): void
    {
        if ($this->hasRecommendedGroup($group)) return;

        $this->recommendedGroups->add($group);
    }

    public function hasRecommendedGroup(ParticipantGroup $group): bool
    {
        foreach ($this->recommendedGroups as $existingGroup) {
            if (EntityUtils::isSameEntity($existingGroup, $group)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return ParticipantGroup[]
     */
    public function getRecommendedGroups(): array
    {
        return $this->recommendedGroups->getValues();
    }

    public function getFrom(): ?string
    {
        return $this->from;
    }

    public function setFrom(?string $from): void
    {
        $this->from = $from;
    }

    public function getRecipients(): ?string
    {
        return $this->recipients;
    }

    public function setRecipients(?string $recipients): void
    {
        $this->recipients = $recipients;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): void
    {
        $this->subject = $subject;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): void
    {
        $this->message = $message;
    }
}

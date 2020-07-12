<?php

namespace App\Entity;

use App\Util\EntityUtils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use App\Traits\TimestampableEntity;
use Symfony\Component\Mime\Email;

/**
 * Log of notifications sent via email. Subclass for notifications sent for
 * different purposes.
 *
 * @ORM\Entity(repositoryClass="App\Repository\EmailNotificationRepository")
 * @ORM\Table(name="study_coordinator_notifications")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="discr", type="string")
 * @ORM\DiscriminatorMap({
 *     "clia" = "CliaRecommendationViralNotification",
 *     "nonNegativeViral" = "NonNegativeViralNotification",
 * })
 */
abstract class EmailNotification
{
    use TimestampableEntity;

    /**
     * @var int
     * @ORM\Id()
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * Participant Groups that were recommended for testing by this notification.
     *
     * @ORM\ManyToMany(targetEntity="App\Entity\ParticipantGroup")
     * @ORM\JoinTable(
     *     name="study_coordinator_notification_recommended_groups",
     *     joinColumns={
     *         @ORM\JoinColumn(name="notification_id", referencedColumnName="id", onDelete="CASCADE")
     *     },
     *     inverseJoinColumns={
     *         @ORM\JoinColumn(name="participant_group_id", referencedColumnName="id", onDelete="CASCADE")
     *     }
     * )
     */
    protected $recommendedGroups;

    /**
     * @var string|null
     * @ORM\Column(name="fromAddresses", type="text", nullable=true)
     */
    protected $fromAddresses;

    /**
     * @var string|null
     * @ORM\Column(name="toAddresses", type="text", nullable=true)
     */
    protected $toAddresses;

    /**
     * @var string|null
     * @ORM\Column(name="subject", type="text", nullable=true)
     */
    protected $subject;

    /**
     * @var string|null
     * @ORM\Column(name="message", type="text", nullable=true)
     */
    protected $message;

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

        $to = [];
        foreach ($email->getTo() as $address) {
            $to[] = $address->toString();
        }

        $n = new static();
        $n->setFromAddressesString(implode(', ', $from));
        $n->setToAddressesString(implode(', ', $to));
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

    public function getFromAddressesString(): ?string
    {
        return $this->fromAddresses;
    }

    public function setFromAddressesString(?string $from): void
    {
        $this->fromAddresses = $from;
    }

    public function getToAddressesString(): ?string
    {
        return $this->toAddresses;
    }

    public function setToAddressesString(?string $to): void
    {
        $this->toAddresses = $to;
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

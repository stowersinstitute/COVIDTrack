<?php


namespace App\Entity;

use App\Util\DateUtils;
use Doctrine\ORM\Mapping as ORM;
use App\Traits\TimestampableEntity;
use App\Traits\SoftDeleteableEntity;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity(repositoryClass="App\Entity\KioskRepository")
 * @ORM\Table(name="kiosks")
 *
 * @Gedmo\Loggable(logEntryClass="App\Entity\AuditLog")
 * @Gedmo\SoftDeleteable(fieldName="deletedAt")
 */
class Kiosk
{
    use TimestampableEntity, SoftDeleteableEntity;

    // Kiosk is waiting to be set up
    const STATE_PROVISIONING            = 'PROVISIONING';

    // Waiting for a user to start the checkin process by scanning something
    const STATE_WAITING_DROPOFF_START   = 'WAITING_DROPOFF_START';
    // User is entering data about a tube
    const STATE_TUBE_INPUT              = 'TUBE_INPUT';
    // User needs to confirm their dropoff is complete
    const STATE_DROPOFF_CONFIRM = 'DROPOFF_CONFIRM';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(name="id", type="integer")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="label", type="string", length=255, unique=true, nullable=false)
     *
     * @Gedmo\Versioned
     */
    private $label;

    /**
     * @var string
     *
     * @ORM\Column(name="location", type="string", length=255, nullable=true)
     *
     * @Gedmo\Versioned
     */
    private $location;

    /**
     * @var \DateTimeImmutable The last time this kiosk reported a heartbeat
     *
     * @ORM\Column(name="last_heartbeat_at", type="datetime_immutable", nullable=true)
     */
    private $lastHeartbeatAt;

    /**
     * @var string IP address the most recent heartbeat came from
     *
     * @ORM\Column(name="last_heartbeat_ip", type="string", length=255, nullable=true)
     */
    private $lastHeartbeatIp;

    /**
     * @var string Version ID reported the last time the kiosk checked in
     *
     * @ORM\Column(name="last_heartbeat_version_id", type="string", length=255, nullable=true)
     */
    private $lastHeartbeatVersionId;

    /**
     * @var string See the STATE_ constants
     *
     * @ORM\Column(name="last_heartbeat_state", type="string", length=255, nullable=true)
     */
    private $lastHeartbeatState;

    /**
     * @var int How long the kiosk has been idle with no user interaction
     *
     * @ORM\Column(name="last_heartbeat_idle_seconds", type="integer", nullable=true)
     */
    private $lastHeartbeatIdleSeconds;

    /**
     * @var boolean The kiosk has been provisioned and associated with a device
     *
     * @ORM\Column(name="is_provisioned", type="boolean", nullable=false)
     */
    protected $isProvisioned;

    public function __construct($label)
    {
        $this->label = $label;

        $this->isProvisioned = false;
    }

    public function isStaleHeartbeat() : ?bool
    {
        // No valid response if there's never been a heartbeat
        if ($this->lastHeartbeatAt === null) return null;

        $expectedIntervalSeconds = 60; // expect a heartbeat every X seconds

        // Allow missing ~ 2 heartbeats
        $gracePeriod = round($expectedIntervalSeconds * 2.5);
        $heartbeatStaleBefore = (new \DateTimeImmutable(sprintf('-%s seconds', $gracePeriod)));

        return $this->lastHeartbeatAt < $heartbeatStaleBefore;
    }

    public function getPrnIdleTime() : string
    {
        if ($this->lastHeartbeatIdleSeconds === null) return '';

        return DateUtils::getPrnElapsedSeconds($this->lastHeartbeatIdleSeconds);
    }

    public function getId() : ?int
    {
        return $this->id;
    }

    public function getLabel() : string
    {
        return $this->label;
    }

    /**
     * @param mixed $label
     */
    public function setLabel($label): void
    {
        $this->label = $label;
    }

    public function getLocation() : ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): void
    {
        $this->location = $location;
    }

    public function getLastHeartbeatAt(): ?\DateTimeImmutable
    {
        return $this->lastHeartbeatAt;
    }

    public function setLastHeartbeatAt(?\DateTimeImmutable $lastHeartbeatAt): void
    {
        $this->lastHeartbeatAt = $lastHeartbeatAt;
    }

    public function getLastHeartbeatIp(): ?string
    {
        return $this->lastHeartbeatIp;
    }

    public function setLastHeartbeatIp(string $lastHeartbeatIp): void
    {
        $this->lastHeartbeatIp = $lastHeartbeatIp;
    }

    public function getLastHeartbeatVersionId(): ?string
    {
        return $this->lastHeartbeatVersionId;
    }

    public function setLastHeartbeatVersionId(?string $lastHeartbeatVersionId): void
    {
        $this->lastHeartbeatVersionId = $lastHeartbeatVersionId;
    }

    public function getLastHeartbeatState(): ?string
    {
        return $this->lastHeartbeatState;
    }

    public function setLastHeartbeatState(?string $lastHeartbeatState): void
    {
        $this->lastHeartbeatState = $lastHeartbeatState;
    }

    public function getLastHeartbeatIdleSeconds(): ?int
    {
        return $this->lastHeartbeatIdleSeconds;
    }

    public function setLastHeartbeatIdleSeconds(?int $lastHeartbeatIdleSeconds): void
    {
        $this->lastHeartbeatIdleSeconds = $lastHeartbeatIdleSeconds;
    }

    public function isProvisioned(): bool
    {
        return $this->isProvisioned;
    }

    public function setIsProvisioned(bool $isProvisioned): void
    {
        $this->isProvisioned = $isProvisioned;
    }
}
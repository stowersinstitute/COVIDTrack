<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Tube data user entered during Kiosk Session.
 *
 * @ORM\Entity
 * @ORM\Table(name="kiosk_session_tubes")
 */
class KioskSessionTube
{
    /**
     * @var integer
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * Kiosk Session where this data was entered.
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\KioskSession", inversedBy="tubeData")
     * @ORM\JoinColumn(name="kiosk_session_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $kioskSession;

    /**
     * Tube scanned by user
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Tube", cascade={"persist"})
     * @ORM\JoinColumn(name="tube_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $tube;

    /**
     * Tube::TYPE_* constant, selected by user
     *
     * @var string
     * @ORM\Column(name="tube_type", type="string")
     */
    private $tubeType;

    /**
     * Date and Time selected by user
     *
     * @var \DateTimeImmutable
     * @ORM\Column(name="tube_collected_at", type="datetime_immutable")
     */
    private $collectedAt;

    public function __construct(KioskSession $session, Tube $tube, string $tubeType, \DateTimeImmutable $collectedAt)
    {
        $this->kioskSession = $session;
        $this->tube = $tube;
        $this->tubeType = $tubeType;
        $this->collectedAt = $collectedAt;
    }

    public function getKioskSession(): KioskSession
    {
        return $this->kioskSession;
    }

    public function getTube(): Tube
    {
        return $this->tube;
    }

    public function getTubeType(): string
    {
        return $this->tubeType;
    }

    public function getCollectedAt(): \DateTimeImmutable
    {
        return $this->collectedAt;
    }
}

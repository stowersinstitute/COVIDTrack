<?php

namespace App\Entity;

use App\Traits\SoftDeleteableEntity;
use App\Traits\TimestampableEntity;
use App\Util\EntityUtils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tracks Tubes returned by a Participant at a Kiosk.
 *
 * @ORM\Entity
 * @ORM\Table(name="dropoffs")
 */
class DropOff
{
    use TimestampableEntity, SoftDeleteableEntity;

    /**
     * @var integer
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var ParticipantGroup
     * @ORM\ManyToOne(targetEntity="App\Entity\ParticipantGroup")
     * @ORM\JoinColumn(name="participant_group_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $group;

    /**
     * @var Tube[]|ArrayCollection
     * @ORM\OneToMany(targetEntity="App\Entity\Tube", mappedBy="dropOff")
     */
    private $tubes;

    /**
     * Kiosk Session when this dropoff took place
     *
     * @var KioskSession
     * @ORM\ManyToOne(targetEntity="App\Entity\KioskSession")
     * @ORM\JoinColumn(name="kiosk_session_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $kioskSession;

    public function __construct()
    {
        $this->tubes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGroup(): ?ParticipantGroup
    {
        return $this->group;
    }

    public function setGroup(?ParticipantGroup $group): void
    {
        $this->group = $group;
    }

    /**
     * @return Tube[]
     */
    public function getTubes(): array
    {
        return $this->tubes->getValues();
    }

    /**
     * @internal Use Tube->kioskDropoffComplete() to establish relationship.
     */
    public function addTube(Tube $tube)
    {
        if ($this->hasTube($tube)) return;

        $this->tubes->add($tube);
    }

    public function hasTube(Tube $tube): bool
    {
        foreach ($this->tubes as $existing) {
            if (EntityUtils::isSameEntity($existing, $tube)) {
                return true;
            }
        }

        return false;
    }

    public function getKioskSession(): ?KioskSession
    {
        return $this->kioskSession;
    }

    public function setKioskSession(?KioskSession $session): void
    {
        $this->kioskSession = $session;
    }
}

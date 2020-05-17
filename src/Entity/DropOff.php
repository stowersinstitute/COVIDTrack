<?php

namespace App\Entity;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Traits\SoftDeleteableEntity;
use App\Traits\TimestampableEntity;
use App\Util\EntityUtils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tracks Specimens returned by a Participant at a Kiosk.
 *
 * @ORM\Entity
 * @ORM\Table(name="dropoffs")
 */
class DropOff
{
    use TimestampableEntity, SoftDeleteableEntity;

    const STATUS_INPROCESS = "IN_PROCESS";
    const STATUS_COMPLETE = "COMPLETE";

    /**
     * @var integer
     *
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     * @ORM\Column(name="status", type="string")
     */
    private $status;

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
     * @var string
     * @ORM\Column(name="kiosk", type="string", nullable=true)
     */
    private $kiosk;

    public function __construct()
    {
        $this->tubes = new ArrayCollection();
        $this->status = self::STATUS_INPROCESS;
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
     * @internal Use Tube->kioskDropoff(...) to establish relationship.
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

    public function getKiosk(): ?string
    {
        return $this->kiosk;
    }

    public function setKiosk(?string $kiosk): void
    {
        $this->kiosk = $kiosk;
    }

    public function markCompleted(SpecimenAccessionIdGenerator $specimenIdGen)
    {
        $this->status = self::STATUS_COMPLETE;

        foreach ($this->tubes as $tube) {
            $tube->kioskDropoffComplete($specimenIdGen);
        }
    }

    public function cancel()
    {
        foreach ($this->tubes as $tube) {
            $tube->kioskDropoffCancel();
        }
    }

}

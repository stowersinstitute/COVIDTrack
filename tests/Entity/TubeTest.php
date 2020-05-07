<?php

namespace App\Tests\Entity;

use App\Entity\DropOff;
use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\Tube;
use PHPUnit\Framework\TestCase;

class TubeTest extends TestCase
{
    public function testParticipantDropOffProcess()
    {
        $group = ParticipantGroup::buildExample('GRP-4-TUBES');

        $drop = new DropOff();
        $drop->setGroup($group);

        $tube = new Tube('TUBE-100');

        // Assert values before action
        $this->assertFalse($drop->hasTube($tube));
        $this->assertEmpty($tube->getSpecimen());

        // Action
        $tubeType = Tube::TYPE_BLOOD;
        $collectedAt = new \DateTime('2020-05-20 15:55:26');
        $tube->kioskDropoff($drop, $group, $tubeType, $collectedAt);

        // Assert values after action
        $this->assertSame($group, $tube->getParticipantGroup());
        $this->assertSame($tubeType, $tube->getTubeType());
        $this->assertSame($collectedAt, $tube->getCollectedAt());
        $this->assertInstanceOf(Specimen::class, $tube->getSpecimen());
    }
}

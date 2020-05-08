<?php

namespace App\Tests\Entity;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Entity\DropOff;
use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\Tube;
use PHPUnit\Framework\MockObject\MockObject;
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
        $accessionId = 'SPEC-123';
        $gen = $this->getMockAccessionIdGenerator($accessionId);
        $tubeType = Tube::TYPE_BLOOD;
        $collectedAt = new \DateTime('2020-05-20 15:55:26');
        $tube->kioskDropoff($gen, $drop, $group, $tubeType, $collectedAt);

        // Assert values after action
        $this->assertSame($accessionId, $tube->getSpecimen()->getAccessionId());
        $this->assertSame($group, $tube->getParticipantGroup());
        $this->assertSame($tubeType, $tube->getTubeType());
        $this->assertSame($collectedAt, $tube->getCollectedAt());
        $this->assertInstanceOf(Specimen::class, $tube->getSpecimen());
    }

    /**
     * @param string $accessionId Accession ID to return when calling ->generate() on the mock
     * @return MockObject|SpecimenAccessionIdGenerator
     */
    private function getMockAccessionIdGenerator(string $accessionId): MockObject
    {
        $mock = $this->getMockBuilder(SpecimenAccessionIdGenerator::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('generate')
            ->willReturn($accessionId);

        return $mock;
    }
}
<?php

namespace App\Tests\Entity;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Entity\DropOff;
use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\SpecimenWell;
use App\Entity\Tube;
use App\Entity\WellPlate;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TubeTest extends TestCase
{
    public function testDefaultValues()
    {
        $tube = new Tube('TUBE-100');

        $this->assertFalse($tube->willAllowExternalProcessing());
        $this->assertNull($tube->getExternalProcessingAt());
    }

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
        $tube->kioskDropoffComplete($gen, $drop, $group, $tubeType, $collectedAt);

        // Assert values after action
        $this->assertSame($accessionId, $tube->getSpecimen()->getAccessionId());
        $this->assertSame($group, $tube->getParticipantGroup());
        $this->assertSame($tubeType, $tube->getTubeType());
        $this->assertSame($collectedAt, $tube->getCollectedAt());
        $this->assertInstanceOf(Specimen::class, $tube->getSpecimen());
    }

    public function testAddingToWellPlateRequiresKioskDropoff()
    {
        $plate = WellPlate::buildExample();
        $tube = new Tube('T123');

        $this->expectException(\RuntimeException::class);
        $tube->addToWellPlate($plate, 'A05');
    }

    public function testAddingToWellPlate()
    {
        $group = ParticipantGroup::buildExample('GRP-A');
        $plate = WellPlate::buildExample();
        $tube = new Tube('T123');
        $tube->setSpecimen(Specimen::buildExample('S123'));

        $dropoff = new DropOff();
        $specIdGen = $this->getMockAccessionIdGenerator('S123');
        $tube->kioskDropoffComplete($specIdGen, $dropoff, $group, Tube::TYPE_SALIVA, new \DateTimeImmutable());

        $well = $tube->addToWellPlate($plate, 'A05');

        $this->assertInstanceOf(SpecimenWell::class, $well);
    }

    public function testMarkExternalProcessingNotAllowedForBlood()
    {
        $tube = new Tube('T123');

        $this->assertFalse($tube->willAllowExternalProcessing());

        $group = ParticipantGroup::buildExample('GRP-A');
        $dropoff = new DropOff();
        $specIdGen = $this->getMockAccessionIdGenerator('S123');
        $tube->kioskDropoffComplete($specIdGen, $dropoff, $group, Tube::TYPE_BLOOD, new \DateTimeImmutable());

        $this->assertFalse($tube->willAllowExternalProcessing());
    }

    public function testMarkExternalProcessingAllowedForSaliva()
    {
        $tube = new Tube('T123');

        $this->assertFalse($tube->willAllowExternalProcessing());

        $group = ParticipantGroup::buildExample('GRP-A');
        $dropoff = new DropOff();
        $specIdGen = $this->getMockAccessionIdGenerator('S123');
        $tube->kioskDropoffComplete($specIdGen, $dropoff, $group, Tube::TYPE_SALIVA, new \DateTimeImmutable());

        $this->assertTrue($tube->willAllowExternalProcessing());

        // Dropped-off Tube has a Specimen, we'll assert against it below
        $specimen = $tube->getSpecimen();
        $this->assertNotEmpty($specimen);

        // Pre-conditions to External Processing
        $this->assertEmpty($tube->getExternalProcessingAt());
        $this->assertSame(Specimen::STATUS_RETURNED, $specimen->getStatus());

        $tube->markExternalProcessing();

        // Post-conditions to External Processing
        $this->assertSame($tube->getStatus(), Tube::STATUS_EXTERNAL);
        $this->assertNotEmpty($tube->getExternalProcessingAt());

        // Specimen must also updated to have Status "EXTERNAL"
        $this->assertSame(Specimen::STATUS_EXTERNAL, $specimen->getStatus());
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

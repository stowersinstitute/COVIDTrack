<?php

namespace App\Tests\Entity;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Entity\DropOff;
use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\SpecimenResult;
use App\Entity\SpecimenResultQPCR;
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

        $this->assertFalse($tube->willAllowCheckinDecision());

        $this->assertFalse($tube->willAllowExternalProcessing());
        $this->assertNull($tube->getExternalProcessingAt());

        // Web Hooks
        $this->assertSame(Tube::WEBHOOK_STATUS_PENDING, $tube->getWebHookStatus());
        $this->assertNull($tube->getWebHookLastTriedPublishingAt());
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
        $this->assertTrue($tube->willAllowCheckinDecision());

        $tube->markExternalProcessing();

        // Post-conditions to External Processing
        $this->assertSame($tube->getStatus(), Tube::STATUS_EXTERNAL);
        $this->assertNotEmpty($tube->getExternalProcessingAt());

        // Specimen must also updated to have Status "EXTERNAL"
        $this->assertSame(Specimen::STATUS_EXTERNAL, $specimen->getStatus());

        // Tube still allows an Accepted/Rejected decision
        $this->assertTrue($tube->willAllowCheckinDecision());
    }

    public function testBloodAcceptedCheckin()
    {
        $tube = new Tube('T123');

        $this->assertFalse($tube->willAllowCheckinDecision());

        $group = ParticipantGroup::buildExample('GRP-A');
        $dropoff = new DropOff();
        $specIdGen = $this->getMockAccessionIdGenerator('S123');
        $tube->kioskDropoffComplete($specIdGen, $dropoff, $group, Tube::TYPE_BLOOD, new \DateTimeImmutable());

        $this->assertTrue($tube->willAllowCheckinDecision());

        $this->assertNotSame(Tube::CHECKED_IN_ACCEPTED, $tube->getCheckInDecision());
        $tube->setCheckInDecision(Tube::CHECKED_IN_ACCEPTED);
        $this->assertSame(Tube::CHECKED_IN_ACCEPTED, $tube->getCheckInDecision());
    }

    public function testBloodRejectedCheckin()
    {
        $tube = new Tube('T123');

        $this->assertFalse($tube->willAllowCheckinDecision());

        $group = ParticipantGroup::buildExample('GRP-A');
        $dropoff = new DropOff();
        $specIdGen = $this->getMockAccessionIdGenerator('S123');
        $tube->kioskDropoffComplete($specIdGen, $dropoff, $group, Tube::TYPE_BLOOD, new \DateTimeImmutable());

        $this->assertTrue($tube->willAllowCheckinDecision());

        $this->assertNotSame(Tube::CHECKED_IN_REJECTED, $tube->getCheckInDecision());
        $tube->setCheckInDecision(Tube::CHECKED_IN_REJECTED);
        $this->assertSame(Tube::CHECKED_IN_REJECTED, $tube->getCheckInDecision());
    }

    public function testMarkingForExternalProcessingQueuesForWebHooks()
    {
        $tube = $this->buildTubeDroppedOff(Tube::TYPE_SALIVA);

        $this->assertSame(Tube::WEBHOOK_STATUS_PENDING, $tube->getWebHookStatus());

        $processedAt = new \DateTimeImmutable('2020-06-15 12:33:44');
        $tube->markExternalProcessing($processedAt);

        $this->assertEquals($processedAt, $tube->getExternalProcessingAt());
        $this->assertSame(Tube::WEBHOOK_STATUS_QUEUED, $tube->getWebHookStatus());
    }

    public function testSuccessfulSendingToWebHook()
    {
        $tube = $this->buildTubeDroppedOff(Tube::TYPE_SALIVA);

        $this->assertSame(Tube::WEBHOOK_STATUS_PENDING, $tube->getWebHookStatus());
        $this->assertEquals(null, $tube->getWebHookLastTriedPublishingAt());

        $successReceivedAt = new \DateTimeImmutable('2020-08-16 15:00:00');
        $message = "Success test";
        $tube->setWebHookSuccess($successReceivedAt, $message);

        $this->assertSame(Tube::WEBHOOK_STATUS_SUCCESS, $tube->getWebHookStatus());
        $this->assertEquals($successReceivedAt, $tube->getWebHookLastTriedPublishingAt());
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

    /**
     * @param string $tubeType Tube::TYPE_* status
     */
    private function buildTubeDroppedOff(string $tubeType): Tube
    {
        $tube = new Tube('T123');
        $group = ParticipantGroup::buildExample('GRP-A');
        $dropoff = new DropOff();
        $specIdGen = $this->getMockAccessionIdGenerator('S123');
        $tube->kioskDropoffComplete($specIdGen, $dropoff, $group, $tubeType, new \DateTimeImmutable());

        return $tube;
    }
}

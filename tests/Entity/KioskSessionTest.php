<?php

namespace App\Tests\Entity;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Entity\DropOff;
use App\Entity\Kiosk;
use App\Entity\KioskSession;
use App\Entity\KioskSessionTube;
use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\Tube;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class KioskSessionTest extends TestCase
{
    public function testCreation()
    {
        $kiosk = new Kiosk('Test Kiosk');
        $session = new KioskSession($kiosk);

        $this->assertNotNull($session->getCreatedAt());
        $this->assertNotNull($session->getMostRecentScreen());
    }

    public function testSelectGroup()
    {
        $kiosk = new Kiosk('Test Kiosk');
        $session = new KioskSession($kiosk);

        // Has not completed group entry yet
        $this->assertNull($session->getParticipantGroup());

        // No DropOff grouping Tubes yet
        $this->assertNull($session->getDropOff());

        // Group badge scanned
        $group = ParticipantGroup::buildExample('GRP-1');
        $session->setParticipantGroup($group);

        // Now has group
        $this->assertSame($session->getParticipantGroup(), $group);
    }

    /**
     * Simulates full lifecycle of KioskSession where user would interact
     * with a kiosk, add tubes, review, then finalize their drop off.
     */
    public function testFinishWithMultipleTubes()
    {
        $kiosk = new Kiosk('Test Kiosk');
        $session = new KioskSession($kiosk);

        // Group badge scanned
        $group = ParticipantGroup::buildExample('GRP-1');
        $session->setParticipantGroup($group);

        $this->assertCount(0, $session->getTubeData());

        // First Tube entered
        $tube1 = new Tube('TUBE-100');
        $tubeType1 = Tube::TYPE_BLOOD;
        $collectedAt1 = new \DateTimeImmutable('2020-05-20 4:00pm');
        $sessionTube1 = new KioskSessionTube($session, $tube1, $tubeType1, $collectedAt1);
        $session->addTubeData($sessionTube1);

        $this->assertCount(1, $session->getTubeData());

        // Second Tube entered
        $tube2 = new Tube('TUBE-200');
        $tubeType2 = Tube::TYPE_SALIVA;
        $collectedAt2 = new \DateTimeImmutable('2020-05-21 6:00pm');
        $sessionTube2 = new KioskSessionTube($session, $tube2, $tubeType2, $collectedAt2);
        $session->addTubeData($sessionTube2);

        $this->assertCount(2, $session->getTubeData());

        // Assert values before action...
        // Tube entities themselves do not have Specimen created yet, not until finish()
        $this->assertEmpty($tube1->getSpecimen());
        $this->assertEmpty($tube2->getSpecimen());

        // Action
        $specimenAccessionIds = ['SPEC-ABC', 'SPEC-DEF'];
        $gen = $this->getMockSpecimenAccIdGenerator($specimenAccessionIds);
        $session->finish($gen);

        // Assert values after action...
        // Session has expected values
        $this->assertInstanceOf(DropOff::class, $session->getDropOff());
        $this->assertSame(KioskSession::SCREEN_COMPLETED, $session->getMostRecentScreen());
        $this->assertNotNull($session->getCompletedAt());

        // First Tube has expected values
        $this->assertInstanceOf(Specimen::class, $tube1->getSpecimen());
        $this->assertSame($specimenAccessionIds[0], $tube1->getSpecimen()->getAccessionId());
        $this->assertSame($group, $tube1->getParticipantGroup());
        $this->assertSame($tubeType1, $tube1->getTubeType());
        $this->assertSame($collectedAt1, $tube1->getCollectedAt());

        // Second Tube has expected values
        $this->assertInstanceOf(Specimen::class, $tube2->getSpecimen());
        // Saliva specimen accession IDs should be the same as the tube accession ID
        $this->assertSame($tube2->getAccessionId(), $tube2->getSpecimen()->getAccessionId());
        $this->assertSame($group, $tube2->getParticipantGroup());
        $this->assertSame($tubeType2, $tube2->getTubeType());
        $this->assertSame($collectedAt2, $tube2->getCollectedAt());
    }

    /**
     * @param string[] $accessionId Accession IDs to return when calling ->generate() each time
     * @return MockObject|SpecimenAccessionIdGenerator
     */
    private function getMockSpecimenAccIdGenerator(array $accessionIds): MockObject
    {
        $mock = $this->getMockBuilder(SpecimenAccessionIdGenerator::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('generate')
            ->willReturnOnConsecutiveCalls(...$accessionIds);

        return $mock;
    }
}

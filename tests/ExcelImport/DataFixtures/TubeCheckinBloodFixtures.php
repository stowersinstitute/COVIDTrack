<?php

namespace App\Tests\ExcelImport\DataFixtures;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Entity\DropOff;
use App\Entity\Tube;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Creates data for testing Check-in of Blood Tubes using Excel
 */
class TubeCheckinBloodFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * @var SpecimenAccessionIdGenerator
     */
    private $specimenIdGen;

    /**
     * @var Tube[] Keys Tube.accessionId, Values Tube entity
     */
    private $tubes;

    public function __construct(SpecimenAccessionIdGenerator $specimenIdGen)
    {
        $this->specimenIdGen = $specimenIdGen;
    }

    public function getDependencies()
    {
        return [
            ParticipantGroupFixtures::class,
        ];
    }

    public function load(ObjectManager $em)
    {
        // Create Tubes
        foreach ($this->getData() as $data) {
            $tube = new Tube($data['accessionId']);
            $this->tubes[$tube->getAccessionId()] = $tube;

            $em->persist($tube);
        }

        $em->flush();

        // Check-in Tubes
        foreach ($this->getData() as $data) {
            $tube = $this->tubes[$data['accessionId']];
            if (!$tube) {
                throw new \RuntimeException('Cannot find Tube fixture by accessionId');
            }

            if (!$data['returnAtKiosk']) continue;

            $group = $data['participantGroup'];
            $tubeType = $data['tubeType'];
            $collectedAt = $data['collectedAt'];

            // One tube per drop-off encounter
            $dropOff = new DropOff();

            $tube->kioskDropoffComplete($this->specimenIdGen, $dropOff, $group, $tubeType, $collectedAt);
        }

        $em->flush();
    }

    public function getData(): array
    {
        return [
            [
                'accessionId' => 'TestBloodCheckin0001',
                'returnAtKiosk' => true,
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $this->getReference('tests.group.blue'),
            ],
            [
                'accessionId' => 'TestBloodCheckin0002',
                'returnAtKiosk' => true,
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $this->getReference('tests.group.blue'),
            ],
            [
                'accessionId' => 'TestBloodCheckin0003',
                'returnAtKiosk' => true,
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $this->getReference('tests.group.blue'),
            ],
            [
                'accessionId' => 'TestBloodCheckin0004',
                'returnAtKiosk' => true,
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $this->getReference('tests.group.blue'),
            ],
            [
                'accessionId' => 'TestBloodCheckin0005',
                'returnAtKiosk' => true,
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $this->getReference('tests.group.blue'),
            ],
            [
                'accessionId' => 'TestBloodCheckin0006',
                'returnAtKiosk' => true,
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $this->getReference('tests.group.blue'),
            ],
            [
                'accessionId' => 'TestBloodCheckin0007',
                'returnAtKiosk' => true,
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $this->getReference('tests.group.blue'),
            ],
            [
                'accessionId' => 'TestBloodCheckin0008',
                'returnAtKiosk' => false,
            ],
            [
                'accessionId' => 'TestBloodCheckin0009',
                'returnAtKiosk' => false,
            ],
            [
                'accessionId' => 'TestBloodCheckin0010',
                'returnAtKiosk' => false,
            ],
        ];
    }
}

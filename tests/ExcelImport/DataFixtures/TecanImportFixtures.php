<?php

namespace App\Tests\ExcelImport\DataFixtures;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Entity\DropOff;
use App\Entity\Tube;
use App\Entity\WellPlate;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Creates test data for testing Tecan Import using a CSV
 */
class TecanImportFixtures extends Fixture implements DependentFixtureInterface
{
    public const FIRST_PLATE_BARCODE = 'FirstWellPlate';

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
        // Create a Well Plate for some records
        $plate = new WellPlate(self::FIRST_PLATE_BARCODE);
        $this->setReference('tests.wellPlate.firstPlate', $plate);
        $em->persist($plate);

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

            // Kiosk Dropoff
            $group = $data['participantGroup'];
            $tubeType = $data['tubeType'];
            $collectedAt = $data['collectedAt'];
            $dropOff = new DropOff();
            $tube->kioskDropoffComplete($this->specimenIdGen, $dropOff, $group, $tubeType, $collectedAt);

            // Accepted Check-in
            $checkinUsername = 'test-checkin-user';
            $tube->markAccepted($checkinUsername);

            // Some Tubes are added to a Well Plate to ensure Tecan Import adds a second plate
            if (isset($data['addToWellPlate'])) {
                $tube->addToWellPlate($data['addToWellPlate']);
            }
        }

        $em->flush();
    }

    /**
     * This data must match what's in tecan-import.csv
     */
    public function getData(): array
    {
        return [
            [
                'accessionId' => 'TestTecan0001',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $this->getReference('tests.group.blue'),
            ],
            [
                'accessionId' => 'TestTecan0002',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $this->getReference('tests.group.blue'),
            ],
            [
                'accessionId' => 'TestTecan0003',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $this->getReference('tests.group.blue'),
            ],
            [
                'accessionId' => 'TestTecan0004',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $this->getReference('tests.group.blue'),
            ],
            [
                'accessionId' => 'TestTecan0005',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $this->getReference('tests.group.blue'),
                'addToWellPlate' => $this->getReference('tests.wellPlate.firstPlate'),
            ],
            [
                'accessionId' => 'TestTecan0006',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $this->getReference('tests.group.blue'),
                'addToWellPlate' => $this->getReference('tests.wellPlate.firstPlate'),
            ],
            [
                'accessionId' => 'TestTecan0007',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $this->getReference('tests.group.blue'),
                'addToWellPlate' => $this->getReference('tests.wellPlate.firstPlate'),
            ],
            [
                'accessionId' => 'TestTecan0008',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $this->getReference('tests.group.blue'),
                'addToWellPlate' => $this->getReference('tests.wellPlate.firstPlate'),
            ],
            [
                'accessionId' => 'TestTecan0009',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $this->getReference('tests.group.blue'),
                'addToWellPlate' => $this->getReference('tests.wellPlate.firstPlate'),
            ],
        ];
    }
}

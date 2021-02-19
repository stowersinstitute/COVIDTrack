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
 * Creates test data for testing importing Antibody Results from an Excel workbook
 */
class SpecimenResultAntibodyImporterFixtures extends Fixture implements DependentFixtureInterface
{
    // Must match specimen-antibody-results.xlsx
    public const PLATE_BARCODE_WITH_RESULTS = 'AntibodyResults1';

    /**
     * @var SpecimenAccessionIdGenerator
     */
    private $specimenIdGen;

    public function __construct()
    {
        // Create mock SpecimenAccessionIdGenerator that generates known values
        $this->specimenIdGen = new class extends SpecimenAccessionIdGenerator {
            // Overwrite constructor to not require any params
            public function __construct() {}

            // Overwrite generate() to only generate valid values
            public function generate() {
                if (empty($counter)) {
                    static $counter = 0;
                }

                $counter++;

                // Must match in specimen-antibody-results.xlsx
                // For example: SpecimenAntibodyResults1
                return sprintf("SpecimenAntibodyResults%d", $counter);
            }
        };
    }

    public function getDependencies()
    {
        return [
            ParticipantGroupFixtures::class,
        ];
    }

    public function load(ObjectManager $em)
    {
        // This Well Plate will hold the Specimens
        $resultsWellPlate = new WellPlate(self::PLATE_BARCODE_WITH_RESULTS);
        $em->persist($resultsWellPlate);

        // Simulate printing labels for Tubes
        foreach ($this->getTubeData() as $data) {
            $tube = new Tube($data['accessionId']);
            $this->addReference($data['accessionId'], $tube);

            $em->persist($tube);
        }
        $em->flush();

        // Simulate Tube drop-off and check-in
        foreach ($this->getTubeData() as $data) {
            /** @var Tube $tube */
            $tube = $this->getReference($data['accessionId']);

            // Kiosk Dropoff
            $group = $data['participantGroup'];
            $tubeType = $data['tubeType'];
            $collectedAt = $data['collectedAt'];
            $dropOff = new DropOff();
            // NOTE: Specimen.accessionId generated with known value. See __construct() above
            $tube->kioskDropoffComplete($this->specimenIdGen, $dropOff, $group, $tubeType, $collectedAt);

            // Accepted Check-in
            $checkinUsername = 'test-checkin-user';
            $tube->markAccepted($checkinUsername);

            // Tubes/Specimens added to a Well Plate
            $well = $tube->addToWellPlate($resultsWellPlate, $data['wellPlatePosition']);

            // Add Well Identifier
            $well->setWellIdentifier($data['wellIdentifier']);
        }

        $em->flush();
    }

    /**
     * This data must match what's in specimen-antibody-results.xlsx
     */
    public function getTubeData(): array
    {
        $blueGroup = $this->getReference('tests.group.blue');

        return [
            [
                'accessionId' => 'SpecimenAntibodyResults1',
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $blueGroup,
                'wellIdentifier' => 'G814450900',
                'wellPlatePosition' => 'A3',
            ],
            [
                'accessionId' => 'SpecimenAntibodyResults2',
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $blueGroup,
                'wellIdentifier' => 'G814450901',
                'wellPlatePosition' => 'A4',
            ],
            [
                'accessionId' => 'SpecimenAntibodyResults3',
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $blueGroup,
                'wellIdentifier' => 'G814450902',
                'wellPlatePosition' => 'B4',
            ],
            [
                'accessionId' => 'SpecimenAntibodyResults4',
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $blueGroup,
                'wellIdentifier' => 'G814450903',
                'wellPlatePosition' => 'C1',
            ],
            [
                'accessionId' => 'SpecimenAntibodyResults5',
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $blueGroup,
                'wellIdentifier' => 'G814450904',
                'wellPlatePosition' => 'C04',
            ],
            [
                'accessionId' => 'SpecimenAntibodyResults6',
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $blueGroup,
                'wellIdentifier' => 'G814450905',
                'wellPlatePosition' => 'C05',
            ],
            [
                'accessionId' => 'SpecimenAntibodyResults7',
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $blueGroup,
                'wellIdentifier' => 'G814450907',
                'wellPlatePosition' => 'C07',
            ],
            [
                'accessionId' => 'SpecimenAntibodyResults8',
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $blueGroup,
                'wellIdentifier' => 'G814450908',
                'wellPlatePosition' => 'C08',
            ],
            [
                'accessionId' => 'SpecimenAntibodyResults9',
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $blueGroup,
                'wellIdentifier' => 'G814450909', // Tests will try adding wrong Well Identifier in results file
                'wellPlatePosition' => 'C09',
            ],
            [
                'accessionId' => 'SpecimenAntibodyResults10',
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $blueGroup,
                'wellIdentifier' => 'G814450910',
                'wellPlatePosition' => 'C10',
            ],
        ];
    }
}

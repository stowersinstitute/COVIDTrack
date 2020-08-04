<?php

namespace App\Tests\ExcelImport\DataFixtures;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Entity\DropOff;
use App\Entity\Specimen;
use App\Entity\Tube;
use App\Entity\WellPlate;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Creates test data for testing importing qPCR Results from an Excel workbook
 */
class SpecimenResultQPCRImporterFixtures extends Fixture implements DependentFixtureInterface
{
    // Must match viral-results-with-ct-amp-score.xlsx
    public const PLATE_BARCODE_WITH_RESULTS = 'QPCRResults';

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

            // Overwrite generate() to not be used by this test
            public function generate() {
                throw new \RuntimeException('Called SpecimenAccessionIdGenerator->generate() when not expected. See SpecimenResultQPCRImporterFixtures::__construct()');
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
        $tubes = [];
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

            // Some test cases expect Tube's Specimen to have a specific Specimen ID
            $this->explicitSetSpecimenId($data['specimenAccessionId'], $tube->getSpecimen());

            // Sent to external facility
            $tube->markExternalProcessing($data['externalProcessingAt']);

            // Tubes/Specimens added to a Well Plate
            $tube->addToWellPlate($resultsWellPlate, $data['wellPlatePosition']);
        }

        $em->flush();
    }

    /**
     * This data must match what's in viral-results-with-ct-amp-score.xlsx
     */
    public function getTubeData(): array
    {
        $blueGroup = $this->getReference('tests.group.blue');

        return [
            [
                'accessionId' => 'TubeQPCRResults0001',
                'specimenAccessionId' => 'TubeQPCRResults0001',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'externalProcessingAt' => new \DateTimeImmutable('-1 day 2:00pm'),
                'participantGroup' => $blueGroup,
                'wellPlatePosition' => 'A1',
            ],
            [
                'accessionId' => 'TubeQPCRResults0002',
                'specimenAccessionId' => 'SpecimenId0002',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'externalProcessingAt' => new \DateTimeImmutable('-1 day 2:00pm'),
                'participantGroup' => $blueGroup,
                'wellPlatePosition' => 'C5',
            ],
            [
                'accessionId' => 'TubeQPCRResults0003',
                'specimenAccessionId' => 'TubeQPCRResults0003',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'externalProcessingAt' => new \DateTimeImmutable('-1 day 2:00pm'),
                'participantGroup' => $blueGroup,
                'wellPlatePosition' => 'D6',
            ],
            [
                'accessionId' => 'TubeQPCRResults0004',
                'specimenAccessionId' => 'SpecimenId0004',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'externalProcessingAt' => new \DateTimeImmutable('-1 day 2:00pm'),
                'participantGroup' => $blueGroup,
                'wellPlatePosition' => 'E7',
            ],
            [
                'accessionId' => 'TubeQPCRResults0005',
                'specimenAccessionId' => 'SpecimenId0005',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'externalProcessingAt' => new \DateTimeImmutable('-1 day 2:00pm'),
                'participantGroup' => $blueGroup,
                'wellPlatePosition' => 'F8',
            ],
            [
                'accessionId' => 'TubeQPCRResults0006',
                'specimenAccessionId' => 'SpecimenId0006',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'externalProcessingAt' => new \DateTimeImmutable('-1 day 2:00pm'),
                'participantGroup' => $blueGroup,
                'wellPlatePosition' => 'G9',
            ],
            [
                'accessionId' => 'TubeQPCRResults0007',
                'specimenAccessionId' => 'SpecimenId0007',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'externalProcessingAt' => new \DateTimeImmutable('-1 day 2:00pm'),
                'participantGroup' => $blueGroup,
                'wellPlatePosition' => 'H10',
            ],
        ];
    }

    /**
     * Set Specimen.accessionId for a very special purpose for testing.
     */
    private function explicitSetSpecimenId(string $specimenAccessionId, Specimen $specimen): void
    {
        $ref = new \ReflectionClass($specimen);
        $prop = $ref->getProperty('accessionId');
        $prop->setAccessible(true);
        $prop->setValue($specimen, $specimenAccessionId);
    }
}

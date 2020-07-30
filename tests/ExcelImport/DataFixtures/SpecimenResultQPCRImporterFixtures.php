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

            // Overwrite generate() to only generate valid values
            public function generate() {
                if (empty($counter)) {
                    static $counter = 0;
                }

                $counter++;

                // Must match in viral-results-with-ct-amp-score.xlsx
                // For example: SpecimenQPCRResults1
                return sprintf("SpecimenQPCRResults%d", $counter);
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
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $blueGroup,
                'wellPlatePosition' => 'A1',
            ],
            [
                'accessionId' => 'TubeQPCRResults0002',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $blueGroup,
                'wellPlatePosition' => 'C5',
            ],
            [
                'accessionId' => 'TubeQPCRResults0003',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $blueGroup,
                'wellPlatePosition' => 'D6',
            ],
            [
                'accessionId' => 'TubeQPCRResults0004',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => $blueGroup,
                'wellPlatePosition' => 'E7',
            ],
        ];
    }
}

<?php

namespace App\Tests\ExcelImport;

use App\Entity\ExcelImportWorkbook;
use App\Entity\Specimen;
use App\Entity\SpecimenResultQPCR;
use App\Entity\WellPlate;
use App\ExcelImport\SpecimenResultQPCRImporter;
use App\Tests\BaseDatabaseTestCase;
use App\Tests\ExcelImport\DataFixtures\SpecimenResultQPCRImporterFixtures;

/**
 * Import Viral Results from Excel.
 */
class SpecimenResultQPCRImporterTest extends BaseDatabaseTestCase
{
    public function testProcess()
    {
        $this->loadFixtures([
            SpecimenResultQPCRImporterFixtures::class,
        ]);

        // Make sure the unknown plate is not in the DB, since it will be created by the import.
        $unknownPlate = $this->em->getRepository(WellPlate::class)->findOneByBarcode('UnknownPlate');
        $this->assertEmpty($unknownPlate, 'Unknown well plate should not be in the database.');

        // XLSX file contains both Specimen.accessionId and Tube.accessionId
        // to ensure import can locate Specimen by either
        $workbook = ExcelImportWorkbook::createFromFilePath(__DIR__ . '/workbooks/viral-results-with-ct-amp-score.xlsx');
        $importer = new SpecimenResultQPCRImporter($this->em, $workbook->getFirstWorksheet());

        $processedResults = $importer->process(true);

        // Verify rows with missing required data report as user-readable errors.
        // viral-results-with-ct-amp-score.xlsx has red cells where errors expected.
        $errors = $importer->getErrors();
        $expectedErrors = [
            [
                'rowNumber' => 4,
            ],
            [
                'rowNumber' => 7,
            ],
            [
                'rowNumber' => 10,
            ],
            [
                'rowNumber' => 11,
            ],
            [
                'rowNumber' => 12,
            ],
        ];
//        var_dump($errors);exit;
        // If this assertion fails uncomment above line for easier debugging
        $this->assertCount(count($expectedErrors), $errors);
        foreach ($expectedErrors as $expectedError) {
            $found = false;
            foreach ($errors as $error) {
                if ($error->getRowNumber() === $expectedError['rowNumber']) {
                    $found = true;
                }
            }
            if (!$found) {
                $this->fail(sprintf('Expected to find error for data in Row %d but no error found', $expectedError['rowNumber']));
            }
        }

        // Verify count of records successfully processed without errors
        $this->assertCount(6, $processedResults);
        $this->assertSame(6, $importer->getNumImportedItems());

        // Data must match viral-results-with-ct-amp-score.xlsx
        $ensureHasConclusion = [
            'TubeQPCRResults0001' => SpecimenResultQPCR::CONCLUSION_POSITIVE,
            'SpecimenId0002' => SpecimenResultQPCR::CONCLUSION_RECOMMENDED,
            'TubeQPCRResults0003' => SpecimenResultQPCR::CONCLUSION_NON_NEGATIVE,
            'SpecimenId0005' => SpecimenResultQPCR::CONCLUSION_NEGATIVE,
            'TubeQPCRResults0006' => SpecimenResultQPCR::CONCLUSION_NON_NEGATIVE,
            'TubeQPCRResults0010' => SpecimenResultQPCR::CONCLUSION_POSITIVE,
        ];
        foreach ($processedResults as $result) {
            $conclusion = $ensureHasConclusion[$result->getSpecimenAccessionId()];
            $this->assertSame($conclusion, $result->getConclusion(), sprintf("%s has wrong Conclusion", $result->getSpecimenAccessionId()));
        }

        // Data must match viral-results-with-ct-amp-score.xlsx
        $ensureHasCT1 = [
            'TubeQPCRResults0001' => 'Undetermined',
            'SpecimenId0002' => '100',
            'TubeQPCRResults0003' => 'Undetermined',
            'SpecimenId0005' => 'Undetermined',
            'TubeQPCRResults0006' => 'Undetermined',
            'TubeQPCRResults0010' => 'Undetermined',
        ];
        foreach ($processedResults as $result) {
            $ct1 = $ensureHasCT1[$result->getSpecimenAccessionId()];
            $this->assertSame($ct1, $result->getCT1(), sprintf('%s wrong CT1', $result->getSpecimenAccessionId()));
        }

        // Data must match viral-results-with-ct-amp-score.xlsx
        $ensureHasCT1AmpScore = [
            'TubeQPCRResults0001' => '0',
            'SpecimenId0002' => '1.987654321',
            'TubeQPCRResults0003' => '400',
            'SpecimenId0005' => '0',
            'TubeQPCRResults0006' => '0',
            'TubeQPCRResults0010' => '0',
        ];
        foreach ($processedResults as $result) {
            $ct1AmpScore = $ensureHasCT1AmpScore[$result->getSpecimenAccessionId()];
            $this->assertSame($ct1AmpScore, $result->getCT1AmpScore(), sprintf('%s wrong CT1 Amp Score', $result->getSpecimenAccessionId()));
        }

        // Data must match viral-results-with-ct-amp-score.xlsx
        $ensureHasCT2 = [
            'TubeQPCRResults0001' => 'Undetermined',
            'SpecimenId0002' => 'Undetermined',
            'TubeQPCRResults0003' => '200',
            'SpecimenId0005' => '500',
            'TubeQPCRResults0006' => '500',
            'TubeQPCRResults0010' => '500',
        ];
        foreach ($processedResults as $result) {
            $CT2 = $ensureHasCT2[$result->getSpecimenAccessionId()];
            $this->assertSame($CT2, $result->getCT2(), sprintf('%s wrong CT2', $result->getSpecimenAccessionId()));
        }

        // Data must match viral-results-with-ct-amp-score.xlsx
        $ensureHasCT2AmpScore = [
            'TubeQPCRResults0001' => '0',
            'SpecimenId0002' => '300',
            'TubeQPCRResults0003' => '0',
            'SpecimenId0005' => '600',
            'TubeQPCRResults0006' => '600',
            'TubeQPCRResults0010' => '600',
        ];
        foreach ($processedResults as $result) {
            $CT2AmpScore = $ensureHasCT2AmpScore[$result->getSpecimenAccessionId()];
            $this->assertSame($CT2AmpScore, $result->getCT2AmpScore(), sprintf('%s wrong CT2 Amp Score', $result->getSpecimenAccessionId()));
        }

        // Data must match viral-results-with-ct-amp-score.xlsx
        $ensureHasCT3 = [
//            'SpecimenQPCRResults1' => '20.4396828318414', Real value exceeds PHP's precision
            'TubeQPCRResults0001' => '20.439682831841',
            'SpecimenId0002' => '18.893144729853',
            'TubeQPCRResults0003' => '19.621005213173',
            'SpecimenId0005' => '21.98765432',
            'TubeQPCRResults0006' => '21.98765432',
            'TubeQPCRResults0010' => '21.98765432',
        ];
        foreach ($processedResults as $result) {
            $CT3 = $ensureHasCT3[$result->getSpecimenAccessionId()];
            $this->assertSame($CT3, $result->getCT3(), sprintf('%s wrong CT3', $result->getSpecimenAccessionId()));
        }

        // Data must match viral-results-with-ct-amp-score.xlsx
        $ensureHasCT3AmpScore = [
//            'TubeQPCRResults0001' => '2.05910575758501', Real value exceeds PHP's precision
            'TubeQPCRResults0001' => '2.059105757585',
//            'SpecimenId0002' => '1.73836317116205', Real value exceeds PHP's precision
            'SpecimenId0002' => '1.738363171162',
//            'TubeQPCRResults0003' => '1.96854084629378', Real value exceeds PHP's precision
            'TubeQPCRResults0003' => '1.9685408462938', // NOTE ROUNDED
//            'SpecimenId0005' => '1.96854084629378', Real value exceeds PHP's precision
            'SpecimenId0005' => '0',
            'TubeQPCRResults0006' => '0',
            'TubeQPCRResults0010' => '0',
        ];
        foreach ($processedResults as $result) {
            $CT3AmpScore = $ensureHasCT3AmpScore[$result->getSpecimenAccessionId()];
            $this->assertSame($CT3AmpScore, $result->getCT3AmpScore(), sprintf('%s wrong CT3 Amp Score', $result->getSpecimenAccessionId()));
        }

        // Test that the first two have wells associated and the second two do not
        $ensureHasWellPlateBarcode = [
            'TubeQPCRResults0001' => SpecimenResultQPCRImporterFixtures::PLATE_BARCODE_WITH_RESULTS,
            'SpecimenId0002' => SpecimenResultQPCRImporterFixtures::PLATE_BARCODE_WITH_RESULTS,
            'TubeQPCRResults0003' => SpecimenResultQPCRImporterFixtures::PLATE_BARCODE_WITH_RESULTS,
            'SpecimenId0005' => 'UnknownPlate', // This plate didn't exist in the DB, it was created by the import
            'TubeQPCRResults0006' => 'UnknownPlate', // This plate didn't exist in the DB, it was created by the import
            'TubeQPCRResults0010' => 'UnknownPlate', // This plate didn't exist in the DB, it was created by the import
        ];

        // Verify use the same WellPlate entity
        $useSameWellPlate = [
            // QPCRResults
            ['TubeQPCRResults0001', 'SpecimenId0002'],
            ['TubeQPCRResults0001', 'TubeQPCRResults0003'],

            // UnknownPlate
            ['SpecimenId0005', 'TubeQPCRResults0006'],
            ['SpecimenId0005', 'TubeQPCRResults0010'],
        ];
        foreach ($useSameWellPlate as [$specimenId1, $specimenId2]) {
            $specimen1Plate = $this->findFirstSpecimenWellPlate($specimenId1);
            $specimen2Plate = $this->findFirstSpecimenWellPlate($specimenId2);
            $this->assertSame($specimen1Plate, $specimen2Plate);
        }

        foreach ($processedResults as $result) {
            $wellPlateBarcode = $ensureHasWellPlateBarcode[$result->getSpecimenAccessionId()];
            $this->assertSame($wellPlateBarcode, $result->getWellPlateBarcode());
        }

        $ensureHasWell = [
            'TubeQPCRResults0001' => 'A1',
            'SpecimenId0002' => 'C5',
            'TubeQPCRResults0003' => 'D6',
            'SpecimenId0005' => 'A1', // This well didn't exist in the DB, it was created by the import
            'TubeQPCRResults0006' => 'A2', // This well didn't exist in the DB, it was created by the import
            'TubeQPCRResults0010' => 'A3', // This well didn't exist in the DB, it was created by the import
        ];

        foreach ($processedResults as $result) {
            $well = $ensureHasWell[$result->getSpecimenAccessionId()];
            $this->assertSame($well, $result->getWellPosition());
        }
    }

    private function findFirstSpecimenWellPlate($accessionId): WellPlate
    {
        $specimen = $this->em
            ->getRepository(Specimen::class)
            ->findOneByAccessionId($accessionId);
        if (!$specimen) {
            throw new \RuntimeException('Cannot find Specimen by accessionId ' . $accessionId);
        }

        $plates = $specimen->getWellPlates();

        return array_shift($plates);
    }
}

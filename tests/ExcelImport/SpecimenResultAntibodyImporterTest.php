<?php

namespace App\Tests\ExcelImport;

use App\Entity\ExcelImportWorkbook;
use App\Entity\Specimen;
use App\Entity\SpecimenResultAntibody;
use App\Entity\WellPlate;
use App\ExcelImport\SpecimenResultAntibodyImporter;
use App\Tests\BaseDatabaseTestCase;
use App\Tests\ExcelImport\DataFixtures\SpecimenResultAntibodyImporterFixtures;

class SpecimenResultAntibodyImporterTest extends BaseDatabaseTestCase
{
    public function testProcess()
    {
        $this->loadFixtures([
            SpecimenResultAntibodyImporterFixtures::class,
        ]);

        $workbook = ExcelImportWorkbook::createFromFilePath(__DIR__ . '/workbooks/specimen-antibody-results.xlsx');
        $importer = new SpecimenResultAntibodyImporter($this->em, $workbook->getFirstWorksheet(), $workbook->getFilename());

        $processedResults = $importer->process(true);

        // Verify rows with missing required data report as user-readable errors.
        // specimen-antibody-results.xlsx has red cells where errors expected.
        $errors = $importer->getErrors();
        $expectedErrors = [
            [
                'rowNumber' => 6,
            ],
            [
                'rowNumber' => 7,
            ],
            [
                'rowNumber' => 8,
            ],
            [
                'rowNumber' => 9,
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

        $this->assertCount(3, $processedResults);
        $this->assertSame(3, $importer->getNumImportedItems());

        // Data must match specimen-viral-results.xlsx
        $conclusionMap = [
            'SpecimenAntibodyResults1' => SpecimenResultAntibody::CONCLUSION_NEGATIVE,
            'SpecimenAntibodyResults2' => SpecimenResultAntibody::CONCLUSION_POSITIVE,
            'SpecimenAntibodyResults7' => SpecimenResultAntibody::CONCLUSION_NON_NEGATIVE,
        ];
        foreach ($processedResults as $result) {
            $specimenId = $result->getSpecimenAccessionId();

            $conclusion = $conclusionMap[$specimenId];
            $this->assertSame($conclusion, $result->getConclusion(), $specimenId . ' has wrong Conclusion');
        }

        // Verify Biobank Tube ID
        $biobankTubeMap = [
            'SpecimenAntibodyResults1' => 'G814450900',
            'SpecimenAntibodyResults2' => 'G814450901',
            'SpecimenAntibodyResults7' => 'G814450907',
        ];
        foreach ($processedResults as $result) {
            $specimenId = $result->getSpecimenAccessionId();

            $expected = $biobankTubeMap[$specimenId];
            $this->assertSame($expected, $result->getWellIdentifier(), $specimenId . ' has wrong Well ID');
        }

        // Verify Biobank Barcode
        $biobankBarcodeMap = [
            'SpecimenAntibodyResults1' => 'AntibodyResults1',
            'SpecimenAntibodyResults2' => 'AntibodyResults1',
            'SpecimenAntibodyResults7' => 'AntibodyResults1',
        ];
        foreach ($processedResults as $result) {
            $specimenId = $result->getSpecimenAccessionId();

            $expected = $biobankBarcodeMap[$specimenId];
            $this->assertSame($expected, $result->getWellPlateBarcode(), $specimenId . ' has wrong Biobank Barcode');
        }

        // Verify use the same WellPlate entity
        $useSameWellPlate = [
            ['SpecimenAntibodyResults1', 'SpecimenAntibodyResults2'],
            ['SpecimenAntibodyResults1', 'SpecimenAntibodyResults7'],
        ];
        foreach ($useSameWellPlate as [$specimenId1, $specimenId2]) {
            $specimen1Plate = $this->findFirstSpecimenWellPlate($specimenId1);
            $specimen2Plate = $this->findFirstSpecimenWellPlate($specimenId2);
            $this->assertSame($specimen1Plate, $specimen2Plate);
        }

        // Verify Signal
        $signalMap = [
            'SpecimenAntibodyResults1' => '0',
            'SpecimenAntibodyResults2' => '3',
            'SpecimenAntibodyResults7' => '4',
        ];
        foreach ($processedResults as $result) {
            $specimenId = $result->getSpecimenAccessionId();

            $expected = $signalMap[$specimenId];
            $this->assertSame($expected, $result->getSignal(), $specimenId . ' has wrong Signal');
        }

        // Verify Rejected has no result created and specimen status is updated
        /** @var Specimen $rejectedSpecimen */
        $rejectedSpecimen = $this->em->getRepository(Specimen::class)->findOneByAccessionId('SpecimenAntibodyResults10');
        $this->assertSame(Specimen::STATUS_REJECTED, $rejectedSpecimen->getStatus(), 'A rejected result should set the specimen to rejected');
        foreach ($processedResults as $result) {
            $this->assertNotEquals('SpecimenAntibodyResults10', $result->getSpecimenAccessionId(), 'A rejected result should not create a result record');
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

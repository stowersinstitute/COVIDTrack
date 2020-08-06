<?php

namespace App\Tests\ExcelImport;

use App\Entity\ExcelImportWorkbook;
use App\Entity\SpecimenResultAntibody;
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
        $importer = new SpecimenResultAntibodyImporter($this->em, $workbook->getFirstWorksheet());

        $processedResults = $importer->process(true);

        // Verify rows with missing required data report as user-readable errors.
        // specimen-antibody-results.xlsx has red cells where errors expected.
        $errors = $importer->getErrors();
        $expectedErrors = [
            [
                'rowNumber' => 5,
            ],
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

        $this->assertCount(2, $processedResults); // Count dependent on SpecimenResultAntibodyImporterFixtures::getData()
        $this->assertSame(2, $importer->getNumImportedItems());

        // Data must match specimen-viral-results.xlsx
        $conclusionMap = [
            'SpecimenAntibodyResults1' => SpecimenResultAntibody::CONCLUSION_NEGATIVE,
            'SpecimenAntibodyResults2' => SpecimenResultAntibody::CONCLUSION_POSITIVE,
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
        ];
        foreach ($processedResults as $result) {
            $specimenId = $result->getSpecimenAccessionId();

            $expected = $biobankBarcodeMap[$specimenId];
            $this->assertSame($expected, $result->getWellPlateBarcode(), $specimenId . ' has wrong Biobank Barcode');
        }

        // Verify Signal
        $signalMap = [
            'SpecimenAntibodyResults1' => '0',
            'SpecimenAntibodyResults2' => '3',
        ];
        foreach ($processedResults as $result) {
            $specimenId = $result->getSpecimenAccessionId();

            $expected = $signalMap[$specimenId];
            $this->assertSame($expected, $result->getSignal(), $specimenId . ' has wrong Signal');
        }
    }
}

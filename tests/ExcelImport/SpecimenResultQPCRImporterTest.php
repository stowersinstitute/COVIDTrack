<?php

namespace App\Tests\ExcelImport;

use App\Entity\ExcelImportWorkbook;
use App\Entity\SpecimenResultQPCR;
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

        $workbook = ExcelImportWorkbook::createFromFilePath(__DIR__ . '/workbooks/viral-results-with-ct-amp-score.xlsx');
        $importer = new SpecimenResultQPCRImporter($this->em, $workbook->getFirstWorksheet());

        $processedResults = $importer->process(true);

        $this->assertSame([], $importer->getErrors(), 'Import has errors when not expected to have any');
        $this->assertCount(4, $processedResults); // Count dependent on SpecimenResultQPCRImporterFixtures::getData()
        $this->assertSame(4, $importer->getNumImportedItems());

        // Data must match viral-results-with-ct-amp-score.xlsx
        $ensureHasConclusion = [
            'TubeQPCRResults0001' => SpecimenResultQPCR::CONCLUSION_POSITIVE,
            'SpecimenId0002' => SpecimenResultQPCR::CONCLUSION_RECOMMENDED,
            'TubeQPCRResults0003' => SpecimenResultQPCR::CONCLUSION_NON_NEGATIVE,
            'SpecimenId0004' => SpecimenResultQPCR::CONCLUSION_NEGATIVE,
        ];
        foreach ($processedResults as $result) {
            $conclusion = $ensureHasConclusion[$result->getSpecimenAccessionId()];
            $this->assertSame($conclusion, $result->getConclusion());
        }

        // Data must match viral-results-with-ct-amp-score.xlsx
        $ensureHasCT1 = [
            'TubeQPCRResults0001' => 'Undetermined',
            'SpecimenId0002' => '100',
            'TubeQPCRResults0003' => 'Undetermined',
            'SpecimenId0004' => 'Undetermined',
        ];
        foreach ($processedResults as $result) {
            $ct1 = $ensureHasCT1[$result->getSpecimenAccessionId()];
            $this->assertSame($ct1, $result->getCT1(), sprintf($result->getSpecimenAccessionId() . ' wrong CT1'));
        }

        // Data must match viral-results-with-ct-amp-score.xlsx
        $ensureHasCT1AmpScore = [
            'TubeQPCRResults0001' => '0',
            'SpecimenId0002' => '1.987654321',
            'TubeQPCRResults0003' => '400',
            'SpecimenId0004' => '0',
        ];
        foreach ($processedResults as $result) {
            $ct1AmpScore = $ensureHasCT1AmpScore[$result->getSpecimenAccessionId()];
            $this->assertSame($ct1AmpScore, $result->getCT1AmpScore(), sprintf($result->getSpecimenAccessionId() . ' wrong CT1 Amp Score'));
        }

        // Data must match viral-results-with-ct-amp-score.xlsx
        $ensureHasCT2 = [
            'TubeQPCRResults0001' => 'Undetermined',
            'SpecimenId0002' => 'Undetermined',
            'TubeQPCRResults0003' => '200',
            'SpecimenId0004' => '500',
        ];
        foreach ($processedResults as $result) {
            $CT2 = $ensureHasCT2[$result->getSpecimenAccessionId()];
            $this->assertSame($CT2, $result->getCT2(), sprintf($result->getSpecimenAccessionId() . ' wrong CT2'));
        }

        // Data must match viral-results-with-ct-amp-score.xlsx
        $ensureHasCT2AmpScore = [
            'TubeQPCRResults0001' => '0',
            'SpecimenId0002' => '300',
            'TubeQPCRResults0003' => '0',
            'SpecimenId0004' => '600',
        ];
        foreach ($processedResults as $result) {
            $CT2AmpScore = $ensureHasCT2AmpScore[$result->getSpecimenAccessionId()];
            $this->assertSame($CT2AmpScore, $result->getCT2AmpScore(), sprintf($result->getSpecimenAccessionId() . ' wrong CT2 Amp Score'));
        }

        // Data must match viral-results-with-ct-amp-score.xlsx
        $ensureHasCT3 = [
//            'SpecimenQPCRResults1' => '20.4396828318414', Real value exceeds PHP's precision
            'TubeQPCRResults0001' => '20.439682831841',
            'SpecimenId0002' => '18.893144729853',
            'TubeQPCRResults0003' => '19.621005213173',
            'SpecimenId0004' => '21.98765432',
        ];
        foreach ($processedResults as $result) {
            $CT3 = $ensureHasCT3[$result->getSpecimenAccessionId()];
            $this->assertSame($CT3, $result->getCT3(), sprintf($result->getSpecimenAccessionId() . ' wrong CT3'));
        }

        // Data must match viral-results-with-ct-amp-score.xlsx
        $ensureHasCT3AmpScore = [
//            'TubeQPCRResults0001' => '2.05910575758501', Real value exceeds PHP's precision
            'TubeQPCRResults0001' => '2.059105757585',
//            'SpecimenId0002' => '1.73836317116205', Real value exceeds PHP's precision
            'SpecimenId0002' => '1.738363171162',
//            'TubeQPCRResults0003' => '1.96854084629378', Real value exceeds PHP's precision
            'TubeQPCRResults0003' => '1.9685408462938', // NOTE ROUNDED
            'SpecimenId0004' => '0',
        ];
        foreach ($processedResults as $result) {
            $CT3AmpScore = $ensureHasCT3AmpScore[$result->getSpecimenAccessionId()];
            $this->assertSame($CT3AmpScore, $result->getCT3AmpScore(), sprintf($result->getSpecimenAccessionId() . ' wrong CT3 Amp Score'));
        }

        // Test that the first two have wells associated and the second two do not
        $ensureHasWellPlateBarcode = [
            'TubeQPCRResults0001' => SpecimenResultQPCRImporterFixtures::PLATE_BARCODE_WITH_RESULTS,
            'SpecimenId0002' => SpecimenResultQPCRImporterFixtures::PLATE_BARCODE_WITH_RESULTS,
            'TubeQPCRResults0003' => null,
            'SpecimenId0004' => null,
        ];

        foreach ($processedResults as $result) {
            $wellPlateBarcode = $ensureHasWellPlateBarcode[$result->getSpecimenAccessionId()];
            $this->assertSame($wellPlateBarcode, $result->getWellPlateBarcode());
        }

        $ensureHasWell = [
            'TubeQPCRResults0001' => 'A1',
            'SpecimenId0002' => 'C5',
            'TubeQPCRResults0003' => null,
            'SpecimenId0004' => null,
        ];

        foreach ($processedResults as $result) {
            $well = $ensureHasWell[$result->getSpecimenAccessionId()];
            $this->assertSame($well, $result->getWellPosition());
        }
    }
}

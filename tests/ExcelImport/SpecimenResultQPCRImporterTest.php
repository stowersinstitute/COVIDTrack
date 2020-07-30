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
            'SpecimenQPCRResults1' => SpecimenResultQPCR::CONCLUSION_POSITIVE,
            'SpecimenQPCRResults2' => SpecimenResultQPCR::CONCLUSION_RECOMMENDED,
            'SpecimenQPCRResults3' => SpecimenResultQPCR::CONCLUSION_NON_NEGATIVE,
            'SpecimenQPCRResults4' => SpecimenResultQPCR::CONCLUSION_NEGATIVE,
        ];
        foreach ($processedResults as $result) {
            $conclusion = $ensureHasConclusion[$result->getSpecimenAccessionId()];
            $this->assertSame($conclusion, $result->getConclusion());
        }

        // Data must match viral-results-with-ct-amp-score.xlsx
        $ensureHasCT1 = [
            'SpecimenQPCRResults1' => 'Undetermined',
            'SpecimenQPCRResults2' => '100',
            'SpecimenQPCRResults3' => 'Undetermined',
            'SpecimenQPCRResults4' => 'Undetermined',
        ];
        foreach ($processedResults as $result) {
            $ct1 = $ensureHasCT1[$result->getSpecimenAccessionId()];
            $this->assertSame($ct1, $result->getCT1(), sprintf($result->getSpecimenAccessionId() . ' wrong CT1'));
        }

        // Data must match viral-results-with-ct-amp-score.xlsx
        $ensureHasCT1AmpScore = [
            'SpecimenQPCRResults1' => '0',
            'SpecimenQPCRResults2' => '1.987654321',
            'SpecimenQPCRResults3' => '400',
            'SpecimenQPCRResults4' => '0',
        ];
        foreach ($processedResults as $result) {
            $ct1AmpScore = $ensureHasCT1AmpScore[$result->getSpecimenAccessionId()];
            $this->assertSame($ct1AmpScore, $result->getCT1AmpScore(), sprintf($result->getSpecimenAccessionId() . ' wrong CT1 Amp Score'));
        }

        // Data must match viral-results-with-ct-amp-score.xlsx
        $ensureHasCT2 = [
            'SpecimenQPCRResults1' => 'Undetermined',
            'SpecimenQPCRResults2' => 'Undetermined',
            'SpecimenQPCRResults3' => '200',
            'SpecimenQPCRResults4' => '500',
        ];
        foreach ($processedResults as $result) {
            $CT2 = $ensureHasCT2[$result->getSpecimenAccessionId()];
            $this->assertSame($CT2, $result->getCT2(), sprintf($result->getSpecimenAccessionId() . ' wrong CT2'));
        }

        // Data must match viral-results-with-ct-amp-score.xlsx
        $ensureHasCT2AmpScore = [
            'SpecimenQPCRResults1' => '0',
            'SpecimenQPCRResults2' => '300',
            'SpecimenQPCRResults3' => '0',
            'SpecimenQPCRResults4' => '600',
        ];
        foreach ($processedResults as $result) {
            $CT2AmpScore = $ensureHasCT2AmpScore[$result->getSpecimenAccessionId()];
            $this->assertSame($CT2AmpScore, $result->getCT2AmpScore(), sprintf($result->getSpecimenAccessionId() . ' wrong CT2 Amp Score'));
        }

        // Data must match viral-results-with-ct-amp-score.xlsx
        $ensureHasCT3 = [
//            'SpecimenQPCRResults1' => '20.4396828318414', Real value exceeds PHP's precision
            'SpecimenQPCRResults1' => '20.439682831841',
            'SpecimenQPCRResults2' => '18.893144729853',
            'SpecimenQPCRResults3' => '19.621005213173',
            'SpecimenQPCRResults4' => '21.98765432',
        ];
        foreach ($processedResults as $result) {
            $CT3 = $ensureHasCT3[$result->getSpecimenAccessionId()];
            $this->assertSame($CT3, $result->getCT3(), sprintf($result->getSpecimenAccessionId() . ' wrong CT3'));
        }

        // Data must match viral-results-with-ct-amp-score.xlsx
        $ensureHasCT3AmpScore = [
//            'SpecimenQPCRResults1' => '2.05910575758501', Real value exceeds PHP's precision
            'SpecimenQPCRResults1' => '2.059105757585',
//            'SpecimenQPCRResults2' => '1.73836317116205', Real value exceeds PHP's precision
            'SpecimenQPCRResults2' => '1.738363171162',
//            'SpecimenQPCRResults3' => '1.96854084629378', Real value exceeds PHP's precision
            'SpecimenQPCRResults3' => '1.9685408462938', // NOTE ROUNDED
            'SpecimenQPCRResults4' => '0',
        ];
        foreach ($processedResults as $result) {
            $CT3AmpScore = $ensureHasCT3AmpScore[$result->getSpecimenAccessionId()];
            $this->assertSame($CT3AmpScore, $result->getCT3AmpScore(), sprintf($result->getSpecimenAccessionId() . ' wrong CT3 Amp Score'));
        }
    }
}

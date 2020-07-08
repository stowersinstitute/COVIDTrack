<?php

namespace App\Tests\ExcelImport;

use App\Entity\ExcelImportWorkbook;
use App\Entity\SpecimenResultQPCR;
use App\ExcelImport\SpecimenResultQPCRImporter;
use App\Tests\BaseDatabaseTestCase;
use App\Tests\ExcelImport\DataFixtures\SpecimenResultQPCRImporterFixtures;

class SpecimenResultQPCRImporterTest extends BaseDatabaseTestCase
{
    public function testProcess()
    {
        $this->loadFixtures([
            SpecimenResultQPCRImporterFixtures::class,
        ]);

        $workbook = ExcelImportWorkbook::createFromFilePath(__DIR__ . '/workbooks/specimen-results.xlsx');
        $importer = new SpecimenResultQPCRImporter($this->em, $workbook->getFirstWorksheet());

        $processedResults = $importer->process(true);

        $this->assertSame([], $importer->getErrors(), 'Import has errors when not expected to have any');
        $this->assertCount(9, $processedResults); // Count dependent on SpecimenResultQPCRImporterFixtures::getData()
        $this->assertSame(9, $importer->getNumImportedItems());

        // Data must match specimen-results.xlsx
        $ensureHasConclusion = [
            'SpecimenQPCRResults1' => SpecimenResultQPCR::CONCLUSION_POSITIVE,
            'SpecimenQPCRResults2' => SpecimenResultQPCR::CONCLUSION_NEGATIVE,
            'SpecimenQPCRResults3' => SpecimenResultQPCR::CONCLUSION_NEGATIVE,
            'SpecimenQPCRResults4' => SpecimenResultQPCR::CONCLUSION_NEGATIVE,
            'SpecimenQPCRResults5' => SpecimenResultQPCR::CONCLUSION_NEGATIVE,
            'SpecimenQPCRResults6' => SpecimenResultQPCR::CONCLUSION_NEGATIVE,
            'SpecimenQPCRResults7' => SpecimenResultQPCR::CONCLUSION_NEGATIVE,
            'SpecimenQPCRResults8' => SpecimenResultQPCR::CONCLUSION_NON_NEGATIVE,
            'SpecimenQPCRResults9' => SpecimenResultQPCR::CONCLUSION_RECOMMENDED,
        ];
        foreach ($processedResults as $result) {
            $conclusion = $ensureHasConclusion[$result->getSpecimenAccessionId()];
            $this->assertSame($conclusion, $result->getConclusion());
        }
    }
}

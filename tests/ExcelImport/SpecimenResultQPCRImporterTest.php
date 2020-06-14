<?php

namespace App\Tests\ExcelImport;

use App\Entity\ExcelImportWorkbook;
use App\Entity\Tube;
use App\ExcelImport\SpecimenResultQPCRImporter;

class SpecimenResultQPCRImporterTest extends BaseExcelImporterTestCase
{
    public function testProcess()
    {
        $this->markTestSkipped('Not ready for results testing yet');
        $workbook = ExcelImportWorkbook::createFromFilePath(__DIR__ . '/workbooks/tube-checkin.xlsx');
        $em = $this->buildMockEntityManager();
        $importer = new SpecimenResultQPCRImporter($em, $workbook->getFirstWorksheet());

        $tubes = $importer->process(true);

        $this->assertCount(7, $tubes);
        $this->assertFalse($importer->hasErrors());
        $this->assertSame(7, $importer->getNumImportedItems());

        $ensureHasTubeIds = [
            'TEST0001',
            'TEST0002',
            'TEST0003',
            'TEST0004',
            'TEST0005',
            'TEST0006',
            'TEST0007',
        ];
        $processedTubeIds = array_map(function(Tube $T) {
            return $T->getAccessionId();
        }, $tubes);
        $missingTubeIds = array_filter($ensureHasTubeIds, function($mustHaveTubeId) use ($processedTubeIds) {
            return !in_array($mustHaveTubeId, $processedTubeIds);
        });

        // Test Tubes all have Specimens

        $this->assertSame([], $missingTubeIds, 'Processed Tubes missing these Tube IDs');
    }
}

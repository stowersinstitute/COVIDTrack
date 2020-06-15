<?php

namespace App\Tests\ExcelImport;

use App\Entity\ExcelImportWorkbook;
use App\Entity\Tube;
use App\ExcelImport\SpecimenCheckinImporter;
use App\ExcelImport\SpecimenResultQPCRImporter;

class SpecimenCheckinImporterTest extends BaseExcelImporterTestCase
{
    public function testProcess()
    {
        $workbook = ExcelImportWorkbook::createFromFilePath(__DIR__ . '/workbooks/tube-checkin.xlsx');
        $importer = new SpecimenCheckinImporter($this->em, $workbook->getFirstWorksheet());

        $checkedInTubes = $importer->process(true);

        $this->assertFalse($importer->hasErrors(), 'Import has errors when not expected to have any');
        $this->assertCount(5, $importer->getOutput()['accepted']);
        $this->assertCount(2, $importer->getOutput()['rejected']);

        $this->assertSame(7, $importer->getNumImportedItems());
        $this->assertCount(7, $checkedInTubes);

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
        }, $checkedInTubes);
        $missingTubeIds = array_filter($ensureHasTubeIds, function($mustHaveTubeId) use ($processedTubeIds) {
            return !in_array($mustHaveTubeId, $processedTubeIds);
        });

        // Test Tubes all have Specimens

        $this->assertSame([], $missingTubeIds, 'Processed Tubes missing these Tube IDs');
    }
}

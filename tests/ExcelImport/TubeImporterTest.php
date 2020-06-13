<?php

namespace App\Tests\ExcelImport;

use App\Entity\ExcelImportWorkbook;
use App\Entity\Tube;
use App\ExcelImport\TubeImporter;

class TubeImporterTest extends BaseExcelImporterTestCase
{
    public function testProcess()
    {
        $workbook = ExcelImportWorkbook::createFromFilePath(__DIR__ . '/workbooks/tube-importer.xlsx');
        $importer = new TubeImporter($workbook->getFirstWorksheet());
        $importer->setEntityManager($this->buildMockEntityManager());

        $tubes = $importer->process(true);

        $this->assertCount(5, $tubes);
        $this->assertFalse($importer->hasErrors());
        $this->assertSame(5, $importer->getNumImportedItems());

        $ensureHasTubeIds = [
            'TEST0001',
            'TEST0002',
            'TEST0003',
            'TEST0004',
            'TEST0005',
        ];
        $processedTubeIds = array_map(function(Tube $T) {
            return $T->getAccessionId();
        }, $tubes);
        $missingTubeIds = array_filter($ensureHasTubeIds, function($mustHaveTubeId) use ($processedTubeIds) {
            return !in_array($mustHaveTubeId, $processedTubeIds);
        });

        $this->assertSame([], $missingTubeIds, 'Processed Tubes missing these Tube IDs');
    }
}

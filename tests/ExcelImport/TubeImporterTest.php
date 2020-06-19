<?php

namespace App\Tests\ExcelImport;

use App\Entity\ExcelImportWorkbook;
use App\Entity\Tube;
use App\ExcelImport\TubeImporter;
use App\Tests\BaseDatabaseTestCase;

/**
 * Tests importing pre-labeled tubes using Excel.
 */
class TubeImporterTest extends BaseDatabaseTestCase
{
    public function testProcess()
    {
        $workbook = ExcelImportWorkbook::createFromFilePath(__DIR__ . '/workbooks/tube-importer.xlsx');
        $importer = new TubeImporter($workbook->getFirstWorksheet());
        $importer->setEntityManager($this->em);

        // This list should match what's in tube-importer.xlsx
        // Order not important
        $expectedTubeIds = [
            'TESTImport0001',
            'TESTImport0002',
            'TESTImport0003',
            'TESTImport0004',
            'TESTImport0005',
            'TESTImport0006',
            'TESTImport0007',
            'TESTImport0008',
            'TESTImport0009',
            'TESTImport0010',
        ];

        $tubes = $importer->process(true);

        $this->assertCount(count($expectedTubeIds), $tubes);
        $this->assertSame([], $importer->getErrors(), 'Import has errors when not expected to have any');
        $this->assertSame(count($expectedTubeIds), $importer->getNumImportedItems());

        // Verify processed list has expected Tube Accession IDs
        $this->mustContainAllTubeAccessionIds($expectedTubeIds, $tubes);
    }

    public function testErrorConditions()
    {
        $workbook = ExcelImportWorkbook::createFromFilePath(__DIR__ . '/workbooks/tube-importer-with-errors.xlsx');
        $importer = new TubeImporter($workbook->getFirstWorksheet());
        $importer->setEntityManager($this->em);

        // This list should match what's in tube-importer.xlsx
        $expectedTubeIds = [
            'TESTImport0001', // The only valid Tube ID in the import
        ];

        $tubes = $importer->process(true);

        $this->assertCount(count($expectedTubeIds), $tubes);
        $this->assertSame(count($expectedTubeIds), $importer->getNumImportedItems());
        $this->mustContainAllTubeAccessionIds($expectedTubeIds, $tubes);

        $this->assertTrue($importer->hasErrors());
        $this->assertCount(3, $importer->getErrors());
    }

    /**
     * @param string[] $expectedTubeIds Array of expected Tube Accession Ids
     * @param Tube[] $tubes
     */
    private function mustContainAllTubeAccessionIds(array $expectedTubeIds, array $tubes)
    {
        $processedTubeIds = array_map(function(Tube $T) {
            return $T->getAccessionId();
        }, $tubes);
        $missingTubeIds = array_filter($expectedTubeIds, function($mustHaveTubeId) use ($processedTubeIds) {
            return !in_array($mustHaveTubeId, $processedTubeIds);
        });

        $this->assertSame([], $missingTubeIds, 'Processed Tubes missing these Tube IDs');
    }
}

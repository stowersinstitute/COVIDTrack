<?php

namespace App\Tests\ExcelImport;

use App\Entity\ExcelImportWorkbook;
use App\Entity\Tube;
use App\ExcelImport\TubeImporter;
use App\Tests\ExcelImport\DataFixtures\TubeTestFixtures;
use Liip\TestFixturesBundle\Test\FixturesTrait;

/**
 * Tests users can import pre-labeled tubes using Excel.
 */
class TubeImporterTest extends BaseExcelImporterTestCase
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
        $this->assertFalse($importer->hasErrors());
        $this->assertSame(count($expectedTubeIds), $importer->getNumImportedItems());

        // Verify processed list has expected Tube Accession IDs
        $this->mustContainAllTubeAccessionIds($expectedTubeIds, $tubes);
    }

    public function testProcessErrorImportingDuplicateTubeAccessionId()
    {
        // Persist/Flush a known Tube Accession ID from tube-importer.xlsx
        $first = new Tube('TESTImport0002');
        $second = new Tube('TESTImport0003');
        $this->persistAndFlush($first, $second);

        // Do import as normal
        $workbook = ExcelImportWorkbook::createFromFilePath(__DIR__ . '/workbooks/tube-importer.xlsx');
        $importer = new TubeImporter($workbook->getFirstWorksheet());
        $importer->setEntityManager($this->em);

        // This list should match what's in tube-importer.xlsx
        // Order not important
        $expectedTubeIds = [
            'TESTImport0001',
//            'TESTImport0002', //Should not be included because existed before import
//            'TESTImport0003', //Should not be included because existed before import
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
        $this->assertTrue($importer->hasErrors());
        $this->assertSame(count($expectedTubeIds), $importer->getNumImportedItems());

        // Verify processed list has expected Tube Accession IDs
        $this->mustContainAllTubeAccessionIds($expectedTubeIds, $tubes);
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

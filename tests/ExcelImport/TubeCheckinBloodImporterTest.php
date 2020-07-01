<?php

namespace App\Tests\ExcelImport;

use App\Entity\ExcelImportWorkbook;
use App\Entity\Tube;
use App\ExcelImport\TubeCheckinBloodImporter;
use App\Tests\BaseDatabaseTestCase;
use App\Tests\ExcelImport\DataFixtures\TubeCheckinBloodFixtures;

class TubeCheckinBloodImporterTest extends BaseDatabaseTestCase
{
    public function testProcess()
    {
        $this->loadFixtures([
            TubeCheckinBloodFixtures::class,
        ]);

        $workbook = ExcelImportWorkbook::createFromFilePath(__DIR__ . '/workbooks/tube-checkin-blood.xlsx');
        $importer = new TubeCheckinBloodImporter($this->em, $workbook->getFirstWorksheet());

        $checkedInTubes = $importer->process(true);

        $this->assertSame([], $importer->getErrors(), 'Import has errors when not expected to have any');
        $this->assertCount(5, $importer->getOutput()['accepted']);
        $this->assertCount(2, $importer->getOutput()['rejected']);

        $this->assertSame(7, $importer->getNumImportedItems());
        $this->assertCount(7, $checkedInTubes);

        $ensureHasTubeIds = [
            'TestBloodCheckin0001',
            'TestBloodCheckin0002',
            'TestBloodCheckin0003',
            'TestBloodCheckin0004',
            'TestBloodCheckin0005',
            'TestBloodCheckin0006',
            'TestBloodCheckin0007',
        ];
        $processedTubeIds = array_map(function(Tube $T) {
            return $T->getAccessionId();
        }, $checkedInTubes);
        $missingTubeIds = array_filter($ensureHasTubeIds, function($mustHaveTubeId) use ($processedTubeIds) {
            return !in_array($mustHaveTubeId, $processedTubeIds);
        });
        $this->assertSame([], $missingTubeIds, 'Processed Tubes missing these Tube IDs');

        // Ensure all imported Tubes now have a Specimen
        foreach ($checkedInTubes as $tube) {
            $this->assertNotNull($tube->getSpecimen());
            $this->assertNotNull($tube->getSpecimen()->getAccessionId());
        }
    }
}

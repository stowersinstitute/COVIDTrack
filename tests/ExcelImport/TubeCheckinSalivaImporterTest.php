<?php

namespace App\Tests\ExcelImport;

use App\Entity\ExcelImportWorkbook;
use App\Entity\Tube;
use App\ExcelImport\TubeCheckinSalivaImporter;
use App\Tests\BaseDatabaseTestCase;
use App\Tests\ExcelImport\DataFixtures\TubeCheckinSalivaFixtures;

class TubeCheckinSalivaImporterTest extends BaseDatabaseTestCase
{
    public function testProcess()
    {
        $this->loadFixtures([
            TubeCheckinSalivaFixtures::class,
        ]);

        $workbook = ExcelImportWorkbook::createFromFilePath(__DIR__ . '/workbooks/tube-checkin-saliva.xlsx');
        $importer = new TubeCheckinSalivaImporter($this->em, $workbook->getFirstWorksheet());

        $checkedInTubes = $importer->process(true);

        // Verify rows with missing required data report as user-readable errors
        $errors = $importer->getErrors();
        $expectedErrors = [
            [
                'rowNumber' => 5,
            ],
            [
                'rowNumber' => 6,
            ],
            [
                'rowNumber' => 10,
            ],
        ];
//        var_dump($errors);exit;
        $this->assertCount(count($expectedErrors), $errors);
        foreach ($expectedErrors as $expectedError) {
            $found = false;
            foreach ($errors as $error) {
                if ($error->getRowNumber() === $expectedError['rowNumber']) {
                    $found = true;
                }
            }
            if (!$found) {
                $this->fail(sprintf('Expected to find error for Row %d but missing', $expectedError['rowNumber']));
            }
        }

        $this->assertCount(5, $importer->getOutput()['accepted']);
        $this->assertCount(2, $importer->getOutput()['rejected']);

        $this->assertSame(7, $importer->getNumImportedItems());
        $this->assertCount(7, $checkedInTubes);

        $ensureHasTubeIds = [
            'TestCheckin0001',
            'TestCheckin0002',
            'TestCheckin0003',
            'TestCheckin0004',
            'TestCheckin0005',
            'TestCheckin0006',
            'TestCheckin0007',
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

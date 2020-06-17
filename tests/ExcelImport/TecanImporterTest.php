<?php

namespace App\Tests\ExcelImport;

use App\Entity\ExcelImportWorkbook;
use App\Entity\Tube;
use App\Entity\WellPlate;
use App\ExcelImport\TecanImporter;
use App\Tests\ExcelImport\DataFixtures\TecanImportFixtures;

class TecanImporterTest extends BaseExcelImporterTestCase
{
    private const PROCESSED_PLATE_BARCODE = 'TecanPlate1';

    public function testProcess()
    {
        $this->loadFixtures([
            TecanImportFixtures::class,
        ]);

        $workbook = ExcelImportWorkbook::createFromFilePath(__DIR__ . '/workbooks/tecan-import.csv');
        $importer = new TecanImporter($this->em, $workbook->getFirstWorksheet());

        $processedTubes = $importer->process(true);
        $this->em->flush(); // Flush so below records all exist in database

        $this->assertSame([], $importer->getErrors(), 'Import has errors when not expected to have any');
        $this->assertCount(6, $importer->getOutput()['created']);
        $this->assertCount(0, $importer->getOutput()['updated']);

        $this->assertSame(6, $importer->getNumImportedItems());
        $this->assertCount(6, $processedTubes);

        $ensureHasTubeIds = [
            'TestTecan0001',
            'TestTecan0002',
            'TestTecan0003',
            'TestTecan0004',
            'TestTecan0005',
            'TestTecan0006',
        ];
        $processedTubeIds = array_map(function(Tube $T) {
            return $T->getAccessionId();
        }, $processedTubes);
        $missingTubeIds = array_filter($ensureHasTubeIds, function($mustHaveTubeId) use ($processedTubeIds) {
            return !in_array($mustHaveTubeId, $processedTubeIds);
        });
        $this->assertSame([], $missingTubeIds, 'Processed Tubes missing these Tube IDs');

        // Ensure all imported Tubes now have a Specimen
        foreach ($processedTubes as $tube) {
            $specimen = $tube->getSpecimen();
            $this->assertNotNull($specimen);
            $this->assertNotNull($specimen->getAccessionId());
        }

        $expectedPlateBarcodes = [
            'TestTecan0001' => [self::PROCESSED_PLATE_BARCODE],
            'TestTecan0002' => [self::PROCESSED_PLATE_BARCODE],
            'TestTecan0003' => [self::PROCESSED_PLATE_BARCODE],
            'TestTecan0004' => [self::PROCESSED_PLATE_BARCODE],
            'TestTecan0005' => [TecanImportFixtures::FIRST_PLATE_BARCODE, self::PROCESSED_PLATE_BARCODE],
            'TestTecan0006' => [TecanImportFixtures::FIRST_PLATE_BARCODE, self::PROCESSED_PLATE_BARCODE],
        ];

        // Ensure all imported Tubes on imported 1 WellPlate
        foreach ($processedTubes as $tube) {
            $expectedBarcodes = $expectedPlateBarcodes[$tube->getAccessionId()];
            $this->assertCount(count($expectedBarcodes), $tube->getRnaWellPlateBarcodes(), sprintf('Tube %s has unexpected number of Well Plates', $tube->getAccessionId()));
            $this->assertSame($expectedBarcodes, $tube->getRnaWellPlateBarcodes());
        }

        $expectedWellPositions = [
            'TestTecan0001' => 'A1',
            'TestTecan0002' => 'B1',
            'TestTecan0003' => 'C1',
            'TestTecan0004' => 'D1',
            'TestTecan0005' => 'E1',
            'TestTecan0006' => 'F1',
        ];
        $plate = $this->findWellPlate(self::PROCESSED_PLATE_BARCODE);
        foreach ($processedTubes as $tube) {
            $specimen = $tube->getSpecimen();
            $wells = $specimen->getWellsOnPlate($plate);
            $this->assertCount(1, $wells);

            $well = $wells[0];
            $this->assertSame($expectedWellPositions[$tube->getAccessionId()], $well->getPositionAlphanumeric());
        }
    }

    private function findWellPlate(string $barcode): WellPlate
    {
        return $this->em->getRepository(WellPlate::class)->findOneByBarcode($barcode);
    }
}

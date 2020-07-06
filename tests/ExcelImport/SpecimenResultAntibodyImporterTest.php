<?php

namespace App\Tests\ExcelImport;

use App\Entity\ExcelImportWorkbook;
use App\Entity\SpecimenResultAntibody;
use App\ExcelImport\SpecimenResultAntibodyImporter;
use App\Tests\BaseDatabaseTestCase;
use App\Tests\ExcelImport\DataFixtures\SpecimenResultAntibodyImporterFixtures;

class SpecimenResultAntibodyImporterTest extends BaseDatabaseTestCase
{
    public function testProcess()
    {
        $this->loadFixtures([
            SpecimenResultAntibodyImporterFixtures::class,
        ]);

        $workbook = ExcelImportWorkbook::createFromFilePath(__DIR__ . '/workbooks/specimen-antibody-results.xlsx');
        $importer = new SpecimenResultAntibodyImporter($this->em, $workbook->getFirstWorksheet());

        $processedResults = $importer->process(true);

        $this->assertSame([], $importer->getErrors(), 'Import has errors when not expected to have any');
        $this->assertCount(6, $processedResults); // Count dependent on SpecimenResultAntibodyImporterFixtures::getData()
        $this->assertSame(6, $importer->getNumImportedItems());

        // Data must match specimen-viral-results.xlsx
        $conclusionMap = [
            'SpecimenAntibodyResults1' => SpecimenResultAntibody::CONCLUSION_NEGATIVE,
            'SpecimenAntibodyResults2' => SpecimenResultAntibody::CONCLUSION_POSITIVE,
            'SpecimenAntibodyResults3' => SpecimenResultAntibody::CONCLUSION_NEGATIVE,
            'SpecimenAntibodyResults4' => SpecimenResultAntibody::CONCLUSION_NEGATIVE,
            'SpecimenAntibodyResults5' => SpecimenResultAntibody::CONCLUSION_POSITIVE,
            'SpecimenAntibodyResults6' => SpecimenResultAntibody::CONCLUSION_NEGATIVE,
        ];
        foreach ($processedResults as $result) {
            $specimenId = $result->getSpecimenAccessionId();

            $conclusion = $conclusionMap[$specimenId];
            $this->assertSame($conclusion, $result->getConclusion(), $specimenId . ' has wrong Conclusion');
        }

        // Verify Biobank Tube ID
        $biobankTubeMap = [
            'SpecimenAntibodyResults1' => 'G814450900',
            'SpecimenAntibodyResults2' => 'G814450901',
            'SpecimenAntibodyResults3' => 'G814450902',
            'SpecimenAntibodyResults4' => 'G814450903',
            'SpecimenAntibodyResults5' => 'G814450904',
            'SpecimenAntibodyResults6' => 'G814450905',
        ];
        foreach ($processedResults as $result) {
            $specimenId = $result->getSpecimenAccessionId();

            $expected = $biobankTubeMap[$specimenId];
            $this->assertSame($expected, $result->getWellIdentifier(), $specimenId . ' has wrong Well Identifier');
        }

        // TODO: Ensure Biobank Plate ID
        // TODO: Ensure Signal

        // TODO: Ensure Storage Rack / Storage Well?
    }
}

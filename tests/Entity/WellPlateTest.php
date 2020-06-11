<?php

namespace App\Tests\Entity;

use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\SpecimenResultQPCR;
use App\Entity\SpecimenWell;
use App\Entity\WellPlate;
use PHPUnit\Framework\TestCase;
use App\Util\EntityUtils;

class WellPlateTest extends TestCase
{
    public function testCreateWellPlate()
    {
        $barcode = 'BC12345';
        $plate = new WellPlate($barcode);

        $this->assertSame($barcode, $plate->getBarcode());
        $this->assertCount(0, $plate->getWells());
        $this->assertCount(0, $plate->getSpecimens());
    }

    public function testSameWellCanOnlyExistOnceOnPlate()
    {
        $plate = WellPlate::buildExample('BC12345');
        $specimen = Specimen::buildExample('C100');
        $well = new SpecimenWell($plate, $specimen, 10);

        // Well Plate and Well now related
        $this->assertSame($well, $plate->getWellAtPosition(10));

        // Adding multiple more times throws Exception
        $this->expectException(\InvalidArgumentException::class);
        $plate->addWell($well);
    }

    public function testGetSpecimensMethodAccountsForSameSpecimenMultipleTimesOnPlate()
    {
        $plate = WellPlate::buildExample('BC12345');

        // This specimen will be added in multiple wells on plate
        $specimen = Specimen::buildExample('C101');

        $well1 = new SpecimenWell($plate, $specimen, 1);
        $well2 = new SpecimenWell($plate, $specimen, 2);
        $well3 = new SpecimenWell($plate, $specimen, 3);

        // Verify Well count is correct
        $this->assertCount(3, $plate->getWells());
        $this->assertTrue($plate->hasWell($well1));
        $this->assertTrue($plate->hasWell($well2));
        $this->assertTrue($plate->hasWell($well3));

        // But the same Specimen only returned once
        $this->assertCount(1, $plate->getSpecimens());
    }
}

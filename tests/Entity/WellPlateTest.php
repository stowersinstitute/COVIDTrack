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
        $well = new SpecimenWell($plate, $specimen, 'B2');

        // Well Plate and Well now related
        $this->assertSame($well, $plate->getWellAtPosition($well->getPositionAlphanumeric()));

        // Adding multiple more times throws Exception
        $this->expectException(\InvalidArgumentException::class);
        $plate->addWell($well);
    }

    public function testGetSpecimensMethodAccountsForSameSpecimenMultipleTimesOnPlate()
    {
        $plate = WellPlate::buildExample('BC12345');

        // This specimen will be added in multiple wells on plate
        $specimen = Specimen::buildExample('C101');

        $well1 = new SpecimenWell($plate, $specimen, 'A1');
        $well2 = new SpecimenWell($plate, $specimen, 'A2');
        $well3 = new SpecimenWell($plate, $specimen, 'A3');

        // Verify Well count is correct
        $this->assertCount(3, $plate->getWells());
        $this->assertTrue($plate->hasWell($well1));
        $this->assertTrue($plate->hasWell($well2));
        $this->assertTrue($plate->hasWell($well3));

        // But the same Specimen only returned once
        $this->assertCount(1, $plate->getSpecimens());
    }

    public function testGetWellsOrderedByWellPosition()
    {
        $plate = WellPlate::buildExample('BC12345');
        $specimen = Specimen::buildExample('C101');

        $well2 = new SpecimenWell($plate, $specimen, 'A11');
        $well1 = new SpecimenWell($plate, $specimen, 'A9');
        $well0 = new SpecimenWell($plate, $specimen, 'A1');
        $well4 = new SpecimenWell($plate, $specimen, 'G8');
        $well3 = new SpecimenWell($plate, $specimen, 'C12');

        $wells = $plate->getWells();
        $this->assertCount(5, $wells);

        $this->assertSame($wells[0]->getPositionAlphanumeric(), $well0->getPositionAlphanumeric());
        $this->assertSame($wells[1]->getPositionAlphanumeric(), $well1->getPositionAlphanumeric());
        $this->assertSame($wells[2]->getPositionAlphanumeric(), $well2->getPositionAlphanumeric());
        $this->assertSame($wells[3]->getPositionAlphanumeric(), $well3->getPositionAlphanumeric());
        $this->assertSame($wells[4]->getPositionAlphanumeric(), $well4->getPositionAlphanumeric());
    }
}

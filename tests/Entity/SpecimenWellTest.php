<?php

namespace App\Tests\Entity;

use App\Entity\Specimen;
use App\Entity\SpecimenWell;
use App\Entity\WellPlate;
use PHPUnit\Framework\TestCase;

class SpecimenWellTest extends TestCase
{
    public function testCreateSpecimenWellWithoutPosition()
    {
        $plateBarcode = 'BC100';
        $plate = WellPlate::buildExample($plateBarcode);

        $specimenAccessionId = 'SPEC999';
        $specimen = Specimen::buildExample($specimenAccessionId);

        $well = new SpecimenWell($plate, $specimen);

        $this->assertSame($plate, $well->getWellPlate());
        $this->assertSame($plateBarcode, $well->getWellPlateBarcode());

        $this->assertSame($specimen, $well->getSpecimen());

        // No result
        $this->assertNull($well->getResultQPCR());

        // No position
        $this->assertNull($well->getPosition());
    }

    public function testCreateSpecimenWellWithPosition()
    {
        $plateBarcode = 'BC101';
        $plate = WellPlate::buildExample($plateBarcode);

        $specimenAccessionId = 'SPEC888';
        $specimen = Specimen::buildExample($specimenAccessionId);

        $position = 55;
        $well = new SpecimenWell($plate, $specimen, $position);

        $this->assertSame($plate, $well->getWellPlate());
        $this->assertSame($plateBarcode, $well->getWellPlateBarcode());

        $this->assertSame($specimen, $well->getSpecimen());

        // No result
        $this->assertNull($well->getResultQPCR());

        // Has position
        $this->assertSame($position, $well->getPosition());
    }

    public function testPlatePreventsWellsAtSamePosition()
    {
        $plate = WellPlate::buildExample('BC102');

        $specimen1 = Specimen::buildExample('SPEC1');
        $specimen2 = Specimen::buildExample('SPEC2');

        // Add Specimen to a specific position
        $position = 10;
        $well1 = new SpecimenWell($plate, $specimen1, $position);

        // Add Specimen to duplicate position should throw Exception
        $this->expectException(\InvalidArgumentException::class);
        new SpecimenWell($plate, $specimen2, $position);
    }

    public function testPlateAllowsMultipleSameSpecimenWithoutPosition()
    {
        $plate = WellPlate::buildExample('BC102');

        $specimen = Specimen::buildExample('SPEC1');

        // Add Specimen but without a position
        $well1 = new SpecimenWell($plate, $specimen);
        $well2 = new SpecimenWell($plate, $specimen);
        $well3 = new SpecimenWell($plate, $specimen);

        $this->assertFalse($well1->isSame($well2));
        $this->assertFalse($well1->isSame($well3));
        $this->assertFalse($well2->isSame($well3));
    }

    public function testPreventsEditingWellPositionCollisions()
    {
        $plate = WellPlate::buildExample('BC102');

        $specimen = Specimen::buildExample('SPEC1');

        // Add Specimen but without a position
        $well1 = new SpecimenWell($plate, $specimen);
        $well2 = new SpecimenWell($plate, $specimen);
        $well3 = new SpecimenWell($plate, $specimen, 30);

        // OK to position in an open well
        $well1->setPosition(10);

        // But assigning to occupied well not allowed
        $this->expectException(\InvalidArgumentException::class);
        $well2->setPosition(10);
    }

    public function testGetWellPlatePositionDisplayString()
    {
        $plateBarcode = 'BC101';
        $plate = WellPlate::buildExample($plateBarcode);

        $specimen = Specimen::buildExample('SPEC888');

        $well = new SpecimenWell($plate, $specimen);

        // Verify display string when doesn't have Position
        $this->assertSame($plateBarcode, $well->getWellPlatePositionDisplayString());

        // Now add position and verify display string includes it
        $well->setPosition(56);
        $this->assertSame('BC101 / 56', $well->getWellPlatePositionDisplayString());
    }
}

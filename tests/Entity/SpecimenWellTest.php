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

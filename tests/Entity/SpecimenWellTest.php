<?php

namespace App\Tests\Entity;

use App\Entity\Specimen;
use App\Entity\SpecimenResultAntibody;
use App\Entity\SpecimenResultQPCR;
use App\Entity\SpecimenWell;
use App\Entity\WellPlate;
use PHPUnit\Framework\TestCase;

class SpecimenWellTest extends TestCase
{
    public function testToString()
    {
        // Well Plate Barcode contained in string
        $plateBarcode = 'BC100';
        $plate = WellPlate::buildExample($plateBarcode);
        $specimen = Specimen::buildExample('S100');
        $well = new SpecimenWell($plate, $specimen);

        $this->assertStringContainsString($plateBarcode, (string)$well);

        // Well Position contained in string
        $position = 'A06';
        $well->setPositionAlphanumeric($position);
        $this->assertStringContainsString($position, (string)$well);

    }

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

        // No results
        $this->assertFalse($well->hasResultsQPCR());
        $this->assertFalse($well->hasResultsAntibody());

        // No well identifier
        $this->assertNull($well->getWellIdentifier());

        // No position
        $this->assertNull($well->getPositionAlphanumeric());
    }

    public function testEmptyStringPositionNotAllowedWhenCreated()
    {
        $plateBarcode = 'BC101';
        $plate = WellPlate::buildExample($plateBarcode);

        $specimenAccessionId = 'SPEC998';
        $specimen = Specimen::buildExample($specimenAccessionId);

        $position = '';

        $this->expectException(\InvalidArgumentException::class);
        $well = new SpecimenWell($plate, $specimen, $position);
    }

    public function testEmptyStringPositionNotAllowedBySetter()
    {
        $plateBarcode = 'BC102';
        $plate = WellPlate::buildExample($plateBarcode);

        $specimenAccessionId = 'SPEC997';
        $specimen = Specimen::buildExample($specimenAccessionId);

        $well = new SpecimenWell($plate, $specimen);

        $position = '';

        $this->expectException(\InvalidArgumentException::class);
        $well->setPositionAlphanumeric($position);
    }

    /**
     * @dataProvider provideAllPositionFormats
     */
    public function testCreateSpecimenWellWithPosition(string $position)
    {
        $plateBarcode = 'BC101';
        $plate = WellPlate::buildExample($plateBarcode);

        $specimenAccessionId = 'SPEC888';
        $specimen = Specimen::buildExample($specimenAccessionId);

        $well = new SpecimenWell($plate, $specimen, $position);

        $this->assertSame($plate, $well->getWellPlate());
        $this->assertSame($plateBarcode, $well->getWellPlateBarcode());

        $this->assertSame($specimen, $well->getSpecimen());

        // No results
        $this->assertFalse($well->hasResultsQPCR());
        $this->assertFalse($well->hasResultsAntibody());

        // No well identifier
        $this->assertNull($well->getWellIdentifier());

        // Has position
        $this->assertSame($position, $well->getPositionAlphanumeric());
    }

    public function provideAllPositionFormats()
    {
        return [
            'Without Padded Zero' => ['F2'],
            'With Padded Zero'    => ['F02'],
            'First Tens'          => ['F10'],
            'Max Row Number'      => ['F12'],
        ];
    }

    /**
     * @dataProvider provideIsAtSamePosition
     */
    public function testIsAtSamePosition($position1, $position2, bool $expected)
    {
        $this->markTestSkipped();
        $actual = SpecimenWell::isSamePosition($position1, $position2);

        $this->assertSame($expected, $actual);
    }

    public function provideIsAtSamePosition()
    {
        return [
            'Two NULLs' => [null, null, false],
            'Two Empty Strings' => ['', '', false],
            'Identical Without Leading' => ['G2', 'G2', true],
            'Identical With Leading' => ['G03', 'G03', true],
            'Same Example 1' => ['G4', 'G04', true],
            'Same Example 2' => ['G05', 'G5', true],
            'Identical Double Digit' => ['G11', 'G11', true],
            'Identical Lots of Chars' => ['L8rG8r', 'L8rG8r', true],
        ];
    }

    /**
     * @dataProvider provideAllPositionFormats
     */
    public function testPlatePreventsWellsAtSamePosition(string $position)
    {
        $plate = WellPlate::buildExample('BC102');

        $specimen1 = Specimen::buildExample('SPEC1');
        $specimen2 = Specimen::buildExample('SPEC2');

        // Add Specimen to a specific position
        $well1 = new SpecimenWell($plate, $specimen1, $position);

        // Add Specimen to duplicate position should throw Exception
        $this->expectException(\InvalidArgumentException::class);
        new SpecimenWell($plate, $specimen2, $well1->getPositionAlphanumeric());
    }

    /**
     * @dataProvider providePlateAllowsAnyWellPositionFormat
     */
    public function testPlateAllowsAnyWellPositionFormat(string $position)
    {
        $plate = WellPlate::buildExample('BC102');

        $specimen1 = Specimen::buildExample('SPEC1');
        $specimen2 = Specimen::buildExample('SPEC2');

        // Add Specimen to a specific position
        $well = new SpecimenWell($plate, $specimen1, $position);

        $this->assertSame($position, $well->getPositionAlphanumeric());

        // Add Specimen to duplicate position should throw Exception
        $this->expectException(\InvalidArgumentException::class);
        new SpecimenWell($plate, $specimen2, $well->getPositionAlphanumeric());
    }

    public function providePlateAllowsAnyWellPositionFormat()
    {
        return [
            'With leading zeros' => ['B06'],
            'Random alphanumeric' => ['Gb4RzPq2'],
            'With symbols' => ['@hJkLp512'],
            'With spaces' => ['HJkL p51 2'],
        ];
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
        $well3 = new SpecimenWell($plate, $specimen, 'G2');

        // OK to position in an open well
        $well1->setPositionAlphanumeric('A1');

        // But assigning to occupied well not allowed
        $this->expectException(\InvalidArgumentException::class);
        $well2->setPositionAlphanumeric($well1->getPositionAlphanumeric());
    }

    /**
     * @dataProvider providePreventsEditingWellPositionCollisionsWithLeadingZero
     */
    public function testPreventsEditingWellPositionCollisionsWithLeadingZero(string $position1, string $position2)
    {
        $plate = WellPlate::buildExample('BC102');

        $specimen = Specimen::buildExample('SPEC1');

        // Add Specimen but without a position
        $well1 = new SpecimenWell($plate, $specimen);
        $well2 = new SpecimenWell($plate, $specimen);

        // Add well to position with leading zero
        $well1->setPositionAlphanumeric($position1);

        // But adding a different well to same position (without leading zero) causes error
        $this->expectException(\InvalidArgumentException::class);
        $well2->setPositionAlphanumeric($position2);
    }

    public function providePreventsEditingWellPositionCollisionsWithLeadingZero()
    {
        return [
            'Leading Zero, then Without' => ['G04', 'G4'],
            'Without, then Leading Zero' => ['F6', 'F06'],
        ];
    }

    /**
     * @dataProvider provideIsAtPosition
     */
    public function testIsAtPosition($setPosition, $testPosition, $expected)
    {
        $plate = WellPlate::buildExample();
        $specimen = Specimen::buildExample('S100');

        $well = new SpecimenWell($plate, $specimen, $setPosition);

        $actual = $well->isAtPosition($testPosition);
        $this->assertSame($expected, $actual);
    }

    public function provideIsAtPosition()
    {
        return [
            // SpecimenWell with a NULL position is not in a specific well in the WellPlate,
            // so isAtPosition() will always return false
            'Well not set in a position' => [null, null, false],
            'Test position null' => ['E3', null, false],
            'Set position null' => [null, 'E4', false],

            'Identical' => ['ASDF1', 'ASDF1', true],
            'Test position without leading zero' => ['E05', 'E5', true],
            'Test position with leading zero' => ['E6', 'E06', true],

            'Mismatch' => ['E5', 'H9', false],
            'Mismatch with trailing zero' => ['F10', 'F1', false],
        ];
    }

    public function testGetWellPlatePositionDisplayString()
    {
        $plateBarcode = 'BC101';
        $plate = WellPlate::buildExample($plateBarcode);

        $specimen = Specimen::buildExample('SPEC888');

        $well = new SpecimenWell($plate, $specimen);

        // Verify display string when doesn't have Position
        $this->assertSame($plateBarcode, $well->getWellPlatePositionDisplayString());

        // Add position and verify display string includes it
        $well->setPositionAlphanumeric('C1');
        $this->assertSame('BC101 / C1', $well->getWellPlatePositionDisplayString());
    }

    /**
     * @dataProvider provideGetAlphanumericPositionFromInteger
     */
    public function testGetAlphanumericPositionFromInteger(int $integer, string $expected)
    {
        $actual = SpecimenWell::positionAlphanumericFromInt($integer);

        $this->assertSame($expected, $actual);
    }
    public function provideGetAlphanumericPositionFromInteger()
    {
        return [
            'Row 1, Column 1' => [1, 'A1'],
            'Row 8, Column 1' => [8, 'H1'],
            'Row 1, Column 2' => [9, 'A2'],
            'Row 2, Column 2' => [10, 'B2'],
            'Row 1, Column 12' => [89, 'A12'],
            'Row 8, Column 12' => [96, 'H12'],
        ];
    }

    /**
     * @dataProvider provideGetAlphanumericPositionFromIntegerInvalidArguments
     */
    public function testGetAlphanumericPositionFromIntegerInvalidArguments(int $integer)
    {
        $this->expectException(\InvalidArgumentException::class);
        SpecimenWell::positionAlphanumericFromInt($integer);
    }
    public function provideGetAlphanumericPositionFromIntegerInvalidArguments()
    {
        return [
            'Negative' => [-2],
            'Below lower bound' => [0],
            'Above upper bound' => [97],
        ];
    }

    public function testTracksAddingAndRemovingMultipleViralResults()
    {
        $plate = WellPlate::buildExample();
        $specimen = Specimen::buildExample('S100');
        $specimen->setStatus(Specimen::STATUS_EXTERNAL);
        $well = new SpecimenWell($plate, $specimen, "A4");

        // Default has no Viral Results
        $this->assertFalse($well->hasResultsQPCR());

        // Add 1st result
        $result1 = SpecimenResultQPCR::createFromWell($well, SpecimenResultQPCR::CONCLUSION_POSITIVE);
        $this->assertTrue($well->hasResultsQPCR());
        $this->assertCount(1, $well->getResultsQPCR());

        // Adding same result multiple times still has only 1 result
        $well->addResultQPCR($result1);
        $this->assertCount(1, $well->getResultsQPCR());

        // Add 2nd result
        $result2 = SpecimenResultQPCR::createFromWell($well, SpecimenResultQPCR::CONCLUSION_NEGATIVE);
        $this->assertTrue($well->hasResultsQPCR());
        $this->assertCount(2, $well->getResultsQPCR());

        // Remove 1st result
        $well->removeResultQPCR($result1);
        $this->assertTrue($well->hasResultsQPCR());
        $this->assertCount(1, $well->getResultsQPCR());

        // Remove 2nd result
        $well->removeResultQPCR($result2);
        $this->assertFalse($well->hasResultsQPCR());
        $this->assertCount(0, $well->getResultsQPCR());
    }

    public function testTracksAddingAndRemovingMultipleAntibodyResults()
    {
        $plate = WellPlate::buildExample();
        $specimen = Specimen::buildExample('S101');
        $specimen->setStatus(Specimen::STATUS_EXTERNAL);
        $well = new SpecimenWell($plate, $specimen, "A5");

        // Default has no Antibody Results
        $this->assertFalse($well->hasResultsAntibody());

        // Add 1st result
        $result1 = new SpecimenResultAntibody($well, SpecimenResultAntibody::CONCLUSION_POSITIVE);
        $this->assertTrue($well->hasResultsAntibody());
        $this->assertCount(1, $well->getResultsAntibody());

        // Adding same result multiple times still has only 1 result
        $well->addResultAntibody($result1);
        $this->assertCount(1, $well->getResultsAntibody());

        // Add 2nd result
        $result2 = new SpecimenResultAntibody($well, SpecimenResultAntibody::CONCLUSION_NEGATIVE);
        $this->assertTrue($well->hasResultsAntibody());
        $this->assertCount(2, $well->getResultsAntibody());

        // Remove 1st result
        $well->removeResultAntibody($result1);
        $this->assertTrue($well->hasResultsAntibody());
        $this->assertCount(1, $well->getResultsAntibody());

        // Remove 2nd result
        $well->removeResultAntibody($result2);
        $this->assertFalse($well->hasResultsAntibody());
        $this->assertCount(0, $well->getResultsAntibody());
    }
}

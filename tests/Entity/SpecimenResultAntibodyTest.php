<?php

namespace App\Tests\Entity;

use App\Entity\Specimen;
use App\Entity\SpecimenResult;
use App\Entity\SpecimenResultAntibody;
use App\Entity\SpecimenWell;
use App\Entity\Tube;
use PHPUnit\Framework\TestCase;

class SpecimenResultAntibodyTest extends TestCase
{
    public function provideValidSignal()
    {
        return [
            'NULL' => [null, null],
            'Boolean False' => [false, ''],
            'Boolean True' => [true, '1'],
            'String Zero' => ['0', '0'],
            'String 1' => ['1', '1'],
            'String 400' => ['400', '400'],
            'Decimal 50.25' => [50.25, '50.25'],
            'Int 0' => [0, '0'],
            'Int 1' => [1, '1'],
            'Int 400' => [400, '400'],
        ];
    }

    /**
     * Tests setSignal() method
     *
     * @dataProvider provideValidSignal
     */
    public function testSetSignal($signal, ?string $expected)
    {
        $specimen = Specimen::buildExampleReadyForResults('S100');
        $well = SpecimenWell::buildExample($specimen);
        $result = new SpecimenResultAntibody($well, SpecimenResultAntibody::CONCLUSION_NON_NEGATIVE);

        $result->setSignal($signal);

        $this->assertSame($expected, $result->getSignal());
    }

    public function testDefaultWebHookStatus()
    {
        $specimen = Specimen::buildExampleReadyForResults('S100');
        $well = SpecimenWell::buildExample($specimen);

        // Result given a Conclusion, so at time of writing, record has all data supplied to Web Hook
        $result = new SpecimenResultAntibody($well, SpecimenResultAntibody::CONCLUSION_NON_NEGATIVE);

        $this->assertSame(SpecimenResult::WEBHOOK_STATUS_QUEUED, $result->getWebHookStatus());
    }

    public function testGetTubeAccessionId()
    {
        $tubeAccessionId = 'T0001';
        $tube = new Tube($tubeAccessionId);
        $specimen = Specimen::buildExampleReadyForResults('S100', null, $tube);
        $well = SpecimenWell::buildExample($specimen);

        // Result given a Conclusion, so at time of writing, record has all data supplied to Web Hook
        $result = new SpecimenResultAntibody($well, SpecimenResultAntibody::CONCLUSION_POSITIVE);

        $this->assertSame($tubeAccessionId, $result->getTubeAccessionId());
    }
}

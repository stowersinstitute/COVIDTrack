<?php

namespace App\Tests\Entity;

use App\Entity\Specimen;
use App\Entity\SpecimenResult;
use App\Entity\SpecimenResultQPCR;
use App\Entity\Tube;
use PHPUnit\Framework\TestCase;

class SpecimenResultQPCRTest extends TestCase
{
    public function testDefaultWebHookStatus()
    {
        $specimen = Specimen::buildExampleReadyForResults('S100');

        // Result given a Conclusion, so at time of writing, record has all data supplied to Web Hook
        $result = new SpecimenResultQPCR($specimen, SpecimenResultQPCR::CONCLUSION_POSITIVE);

        $this->assertSame(SpecimenResult::WEBHOOK_STATUS_QUEUED, $result->getWebHookStatus());
    }

    public function testGetTubeAccessionId()
    {
        $tubeAccessionId = 'T0001';
        $tube = new Tube($tubeAccessionId);
        $specimen = Specimen::buildExampleReadyForResults('S100', null, $tube);

        // Result given a Conclusion, so at time of writing, record has all data supplied to Web Hook
        $result = new SpecimenResultQPCR($specimen, SpecimenResultQPCR::CONCLUSION_POSITIVE);

        $this->assertSame($tubeAccessionId, $result->getTubeAccessionId());
    }

    public function testLookupConclusionConstant()
    {
        // When mapped to a valid constant
        $found = SpecimenResultQPCR::lookupConclusionConstant('Detected');
        $this->assertSame(SpecimenResultQPCR::CONCLUSION_POSITIVE, $found);

        // When search text not mapped
        $this->assertNull(SpecimenResultQPCR::lookupConclusionConstant('Some Unknown Text'));
    }

    public function testReturnsConclusionTextDifferentThanConstantValue()
    {
        $specimen = Specimen::buildExampleReadyForResults('S100', null, new Tube('T0001'));
        $result = new SpecimenResultQPCR($specimen, SpecimenResultQPCR::CONCLUSION_POSITIVE);

        $this->assertSame('Detected', $result->getConclusionText());
    }
}

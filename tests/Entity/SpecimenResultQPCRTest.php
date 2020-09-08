<?php

namespace App\Tests\Entity;

use App\Entity\Specimen;
use App\Entity\SpecimenResult;
use App\Entity\SpecimenResultQPCR;
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
}

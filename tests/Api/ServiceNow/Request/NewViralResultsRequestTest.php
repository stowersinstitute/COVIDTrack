<?php

namespace App\Tests\Api\ServiceNow\Request;

use App\Api\ServiceNow\Request\NewViralResultsRequest;
use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\SpecimenResultQPCR;
use App\Entity\SpecimenWell;
use App\Entity\WellPlate;
use PHPUnit\Framework\TestCase;

class NewViralResultsRequestTest extends TestCase
{
    private $lastResultId = 0;

    public function testSerializeToJson()
    {
        $viral = $this->buildFakeResult(SpecimenResultQPCR::CONCLUSION_POSITIVE);

        $createdAt = new \DateTimeImmutable('2020-06-25 15:45:55.987654', new \DateTimeZone('America/Chicago'));
        $viral->setCreatedAt($createdAt);

        $request = new NewViralResultsRequest();
        $request->addResult($viral);

        $json = $request->toJson(\JSON_PRETTY_PRINT);

        // Verify Result createdAt date serialization format:
        // 1. Uses ISO 8601 format
        // 2. Rendered without microseconds (our MySQL database doesn't store them)
        // 3. Printed as UTC instead of -05:00 CDT timezone (15+5 == 20)
        $this->assertContains('2020-06-25T20:45:55Z', $json);
    }

    /**
     * @param string $resultConclusion SpecimenResultQPCR::CONCLUSION_* constant
     */
    private function buildFakeResult(string $resultConclusion): SpecimenResultQPCR
    {
        $group = new ParticipantGroup('GROUP-200', 10);
        $group->setTitle('ABCDEF1234567890');

        $specimen = Specimen::buildExampleReadyForResults('SPEC-100', $group);

        $plate = new WellPlate('WP-BARCODE-123');
        $well = new SpecimenWell($plate, $specimen, 'A4');

        $result = SpecimenResultQPCR::createFromWell($well, $resultConclusion);

        // Force an ID value
        $prop = new \ReflectionProperty($result, 'id');
        $prop->setAccessible(true);
        $prop->setValue($result, ++$this->lastResultId);

        return $result;
    }
}

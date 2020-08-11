<?php

namespace App\Tests\Api\WebHook\Request;

use App\Api\WebHook\Request\NewViralResultsWebHookRequest;
use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\SpecimenResultQPCR;
use App\Entity\SpecimenWell;
use App\Entity\WellPlate;
use PHPUnit\Framework\TestCase;

class NewViralResultsRequestTest extends TestCase
{
    private const SERVICENOW_GROUP_ID = 'SN-Unique-Id-Here';
    private $lastResultId = 100;

    public function testSerializeToJson()
    {
        $viral = $this->buildFakeResult('SPEC-100', SpecimenResultQPCR::CONCLUSION_POSITIVE, 'A4');

        $createdAt = new \DateTimeImmutable('2020-06-25 15:45:55.987654', new \DateTimeZone('America/Chicago'));
        $viral->setCreatedAt($createdAt);

        $request = new NewViralResultsWebHookRequest();
        $request->addResult($viral);

        $request->addResult($this->buildFakeResult('SPEC-101', SpecimenResultQPCR::CONCLUSION_NEGATIVE, 'A5'));

        $json = $request->toJson(\JSON_PRETTY_PRINT);

        echo $json;

        // Verify Result createdAt date serialization format:
        // 1. Uses ISO 8601 format
        // 2. Rendered without microseconds (our MySQL database doesn't store them)
        // 3. Printed as UTC instead of -05:00 CDT timezone (15+5 == 20)
        $this->assertContains('2020-06-25T20:45:55Z', $json);

        $this->assertContains(self::SERVICENOW_GROUP_ID, $json);
    }

    /**
     * @param string $resultConclusion SpecimenResultQPCR::CONCLUSION_* constant
     */
    private function buildFakeResult(string $specimenAccessionId, string $resultConclusion, string $wellPosition): SpecimenResultQPCR
    {
        $group = new ParticipantGroup('GROUP-200', 10);
        $group->setTitle('ABCDEF1234567890');
        $group->setExternalId(self::SERVICENOW_GROUP_ID);
        // Force an ID value
        $prop = new \ReflectionProperty($group, 'id');
        $prop->setAccessible(true);
        $prop->setValue($group, 200);

        $specimen = Specimen::buildExampleReadyForResults($specimenAccessionId, $group);

        $plate = new WellPlate('WP-BARCODE-123');
        $well = new SpecimenWell($plate, $specimen, $wellPosition);

        $result = SpecimenResultQPCR::createFromWell($well, $resultConclusion);

        // Force an ID value
        $prop = new \ReflectionProperty($result, 'id');
        $prop->setAccessible(true);
        $prop->setValue($result, ++$this->lastResultId);

        return $result;
    }
}

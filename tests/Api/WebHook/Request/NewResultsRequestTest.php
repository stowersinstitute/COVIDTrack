<?php

namespace App\Tests\Api\WebHook\Request;

use App\Api\WebHook\Request\NewResultsWebHookRequest;
use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\SpecimenResultAntibody;
use App\Entity\SpecimenResultQPCR;
use App\Entity\SpecimenWell;
use App\Entity\Tube;
use App\Entity\WellPlate;
use PHPUnit\Framework\TestCase;

class NewResultsRequestTest extends TestCase
{
    private const SERVICENOW_GROUP_ID = 'SN-Unique-Id-Here';
    private $lastResultId = 200;

    public function testSerializeToJson()
    {
        $request = new NewResultsWebHookRequest();

        // Viral result
        $viral = $this->buildFakeResultViral('TUBE-100', 'SPEC-100', SpecimenResultQPCR::CONCLUSION_POSITIVE, 'B1');
        $viralCreatedAt = new \DateTimeImmutable('2020-06-25 15:45:55.987654', new \DateTimeZone('America/Chicago'));
        $viral->setCreatedAt($viralCreatedAt);
        $request->addResult($viral);

        // Antibody result
        $antibody = $this->buildFakeResultAntibody('TUBE-200', 'SPEC-200', SpecimenResultAntibody::CONCLUSION_POSITIVE, 'A1');
        $antibodyCreatedAt = new \DateTimeImmutable('2020-06-26 15:45:56.987654', new \DateTimeZone('America/Chicago'));
        $antibody->setCreatedAt($antibodyCreatedAt);
        $request->addResult($antibody);

        // Add more Viral with all possible conclusions
        $request->addResult($this->buildFakeResultViral('TUBE-101', 'SPEC-101', SpecimenResultQPCR::CONCLUSION_NEGATIVE, 'B2'));
        $request->addResult($this->buildFakeResultViral('TUBE-102', 'SPEC-102', SpecimenResultQPCR::CONCLUSION_NON_NEGATIVE, 'B3'));
        $request->addResult($this->buildFakeResultViral('TUBE-103', 'SPEC-103', SpecimenResultQPCR::CONCLUSION_RECOMMENDED, 'B4'));

        // Add more Antibody with all possible conclusions
        $request->addResult($this->buildFakeResultAntibody('TUBE-201', 'SPEC-201', SpecimenResultAntibody::CONCLUSION_NEGATIVE, 'A2'));
        $request->addResult($this->buildFakeResultAntibody('TUBE-202', 'SPEC-202', SpecimenResultAntibody::CONCLUSION_NON_NEGATIVE, 'A3'));

        $json = $request->toJson(\JSON_PRETTY_PRINT);

        // Verify Result createdAt date serialization format:
        // 1. Uses ISO 8601 format
        // 2. Rendered without microseconds (our MySQL database doesn't store them)
        // 3. Printed as UTC instead of -05:00 CDT timezone (15+5 == 20)

        // Viral createdAt is UTC
        $this->assertStringContainsString('2020-06-25T20:45:55Z', $json);
        // Antibody createdAt is UTC
        $this->assertStringContainsString('2020-06-26T20:45:56Z', $json);

        $this->assertStringContainsString(self::SERVICENOW_GROUP_ID, $json);

        // Verify Tube IDs found
        $this->assertStringContainsString('TUBE-100', $json);
        $this->assertStringContainsString('TUBE-101', $json);
        $this->assertStringContainsString('TUBE-102', $json);
        $this->assertStringContainsString('TUBE-103', $json);
        $this->assertStringContainsString('TUBE-200', $json);
        $this->assertStringContainsString('TUBE-201', $json);
        $this->assertStringContainsString('TUBE-202', $json);
    }

    /**
     * @param string $conclusion SpecimenResultAntibody::CONCLUSION_* constant
     */
    private function buildFakeResultAntibody(string $tubeAccessionId, string $specimenAccessionId, string $conclusion, string $wellPosition): SpecimenResultAntibody
    {
        $group = $this->buildParticipantGroup();

        $tube = new Tube($tubeAccessionId);
        $specimen = Specimen::buildExampleReadyForResults($specimenAccessionId, $group, $tube);

        $plate = new WellPlate('WP-BARCODE-123');
        $well = new SpecimenWell($plate, $specimen, $wellPosition);

        $result = new SpecimenResultAntibody($well, $conclusion);

        // Force an ID value
        $prop = new \ReflectionProperty($result, 'id');
        $prop->setAccessible(true);
        $prop->setValue($result, ++$this->lastResultId);

        return $result;
    }

    /**
     * @param string $conclusion SpecimenResultQPCR::CONCLUSION_* constant
     */
    private function buildFakeResultViral(string $tubeAccessionId, string $specimenAccessionId, string $conclusion, string $wellPosition): SpecimenResultQPCR
    {
        $group = $this->buildParticipantGroup();

        $tube = new Tube($tubeAccessionId);
        $specimen = Specimen::buildExampleReadyForResults($specimenAccessionId, $group, $tube);

        $plate = new WellPlate('WP-BARCODE-123');
        $well = new SpecimenWell($plate, $specimen, $wellPosition);

        $result = SpecimenResultQPCR::createFromWell($well, $conclusion);

        // Force an ID value
        $prop = new \ReflectionProperty($result, 'id');
        $prop->setAccessible(true);
        $prop->setValue($result, ++$this->lastResultId);

        return $result;
    }

    private function buildParticipantGroup(): ParticipantGroup
    {
        if (!empty($group)) {
            return $group;
        }

        // All results belong to the same Group
        static $group = null;
        $group = new ParticipantGroup('GROUP-300', 10);
        $group->setTitle('ABCDEF1234567890');
        $group->setExternalId(self::SERVICENOW_GROUP_ID);

        // Force an ID value
        $prop = new \ReflectionProperty($group, 'id');
        $prop->setAccessible(true);
        $prop->setValue($group, 300);

        return $group;
    }
}

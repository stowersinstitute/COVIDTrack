<?php

namespace App\Tests\Entity;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Entity\DropOff;
use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\SpecimenResultQPCR;
use App\Entity\Tube;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use App\Util\EntityUtils;

class SpecimenTest extends TestCase
{
    public function testCreateSpecimen()
    {
        $group = ParticipantGroup::buildExample('G123', 5);
        $s = new Specimen('CID123', $group);

        $this->assertSame('CID123', $s->getAccessionId());
        $this->assertSame($s->getParticipantGroup(), $group);
    }

    public function testGetNewestQPCRResult()
    {
        $s = Specimen::buildExample('C100');

        $r1 = new SpecimenResultQPCR($s);
        $r1->setCreatedAt(new \DateTimeImmutable('2020-04-24'));
        $r1->setConclusion(SpecimenResultQPCR::CONCLUSION_NEGATIVE);

        // R2. This is the latest result
        $r2 = new SpecimenResultQPCR($s);
        $r2->setCreatedAt(new \DateTimeImmutable('2020-04-25'));
        $r2->setConclusion(SpecimenResultQPCR::CONCLUSION_POSITIVE);

        // R3. Adding an earlier result does not override R2
        $r3 = new SpecimenResultQPCR($s);
        $r3->setCreatedAt(new \DateTimeImmutable('2020-04-23'));
        $r3->setConclusion(SpecimenResultQPCR::CONCLUSION_RECOMMENDED);

        $this->assertSame($r2, $s->getMostRecentQPCRResult());
    }

    public function testGetCliaTestingText()
    {
        $specimen = Specimen::buildExample('C100');

        // Default when Specimen test results not yet available
        $this->assertSame('Awaiting Results', $specimen->getCliaTestingRecommendedText());

        // Add Pending Result
        $r1 = new SpecimenResultQPCR($specimen);
        $r1->setConclusion(SpecimenResultQPCR::CONCLUSION_PENDING);
        $this->assertSame('Awaiting Results', $specimen->getCliaTestingRecommendedText());

        // Add Negative Result
        $r2 = new SpecimenResultQPCR($specimen);
        $r2->setConclusion(SpecimenResultQPCR::CONCLUSION_NEGATIVE);
        $this->assertSame('No Recommendation', $specimen->getCliaTestingRecommendedText());

        // Add Positive Result
        $r3 = new SpecimenResultQPCR($specimen);
        $r3->setConclusion(SpecimenResultQPCR::CONCLUSION_POSITIVE);
        $this->assertSame('Recommend Diagnostic Testing', $specimen->getCliaTestingRecommendedText());

        // Add Recommended Result
        $r4 = new SpecimenResultQPCR($specimen);
        $r4->setConclusion(SpecimenResultQPCR::CONCLUSION_RECOMMENDED);
        $this->assertSame('Recommend Diagnostic Testing', $specimen->getCliaTestingRecommendedText());

        // Back to Inconclusive Result
        $r5 = new SpecimenResultQPCR($specimen);
        $r5->setConclusion(SpecimenResultQPCR::CONCLUSION_INCONCLUSIVE);
        $this->assertSame('No Recommendation', $specimen->getCliaTestingRecommendedText());
    }

    public function testCreateFromTube()
    {
        $tube = new Tube('TUBE-100');

        $accessionId = 'SPEC-200';
        $gen = $this->getMockAccessionIdGenerator($accessionId);

        $drop = new DropOff();
        $group = ParticipantGroup::buildExample('GRP-1');
        $tubeType = Tube::TYPE_BLOOD;
        $collectedAt = new \DateTime('2020-05-20 15:22:44');
        $tube->kioskDropoff($gen, $drop, $group, $tubeType, $collectedAt);

        $specimen = Specimen::createFromTube($tube, $gen);

        $this->assertSame($accessionId, $tube->getSpecimen()->getAccessionId());
        $this->assertSame($group, $tube->getParticipantGroup());
        $this->assertSame(Specimen::TYPE_BLOOD, $specimen->getType());
        $this->assertEquals($collectedAt, $specimen->getCollectedAt());
        $this->assertTrue(EntityUtils::isSameEntity($tube->getParticipantGroup(), $specimen->getParticipantGroup()));
    }

    /**
     * @param string $accessionId Accession ID to return when calling ->generate() on the mock
     * @return MockObject|SpecimenAccessionIdGenerator
     */
    private function getMockAccessionIdGenerator(string $accessionId): MockObject
    {
        $mock = $this->getMockBuilder(SpecimenAccessionIdGenerator::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('generate')
            ->willReturn($accessionId);

        return $mock;
    }
}

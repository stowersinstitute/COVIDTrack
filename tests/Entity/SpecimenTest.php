<?php

namespace App\Tests\Entity;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Entity\DropOff;
use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\SpecimenResultQPCR;
use App\Entity\SpecimenWell;
use App\Entity\Tube;
use App\Entity\WellPlate;
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

    public function testSpecimenOnlyExistsOnceOnSameWellPlate()
    {
        $specimen = Specimen::buildExample('C100');

        // No results returned when has 0 results
        $this->assertCount(0, $specimen->getWells());
        $this->assertCount(0, $specimen->getWellPlates());
        $this->assertCount(0, $specimen->getQPCRResults());

        // Add first result on Plate ABC
        $plateABC = WellPlate::buildExample('ABC');
        $position1 = 10;
        $well1 = new SpecimenWell($plateABC, $specimen, $position1);
        $conclusion1 = SpecimenResultQPCR::CONCLUSION_NEGATIVE;
        $result1 = new SpecimenResultQPCR($well1, $conclusion1);

        // For now only in 1 Well on 1 Plate
        $this->assertCount(1, $specimen->getWells());
        $this->assertSame($position1, $specimen->getWells()[0]->getPosition());
        $this->assertCount(1, $specimen->getWellPlates());
        $this->assertCount(1, $specimen->getQPCRResults());
        $this->assertSame($conclusion1, $specimen->getQPCRResults(1)[0]->getConclusion());

        // Add second result on Plate ABC
        $position2 = 10;
        $well2 = new SpecimenWell($plateABC, $specimen, $position1);
        $conclusion2 = SpecimenResultQPCR::CONCLUSION_POSITIVE;
        $result2 = new SpecimenResultQPCR($well2, $conclusion2);

        // Still only on 1 Plate and 1 Well
        // Verify Specimen only has one Well
        // Verify Specimen in expected Position
        $this->assertCount(1, $specimen->getWells());
        $this->assertSame($position2, $specimen->getWells()[0]->getPosition());
        $this->assertCount(1, $specimen->getWellPlates());
        $this->assertCount(1, $specimen->getQPCRResults());
        $this->assertSame($conclusion2, $specimen->getQPCRResults(1)[0]->getConclusion());
    }

    public function testGetQPCRResultsWhenEmpty()
    {
        $specimen = Specimen::buildExample('C100');

        // No results returned when has 0 results
        $results = $specimen->getQPCRResults(1);
        $this->assertCount(0, $results);

        // Add first result
        $well1 = SpecimenWell::buildExample($specimen);
        $r1 = new SpecimenResultQPCR($well1, SpecimenResultQPCR::CONCLUSION_NEGATIVE);
        $r1->setCreatedAt(new \DateTimeImmutable('2020-04-24'));

        // Add second result (but it's the most recent created at)
        $well2 = SpecimenWell::buildExample($specimen);
        $r2 = new SpecimenResultQPCR($well2, SpecimenResultQPCR::CONCLUSION_POSITIVE);
        $r2->setCreatedAt(new \DateTimeImmutable('2020-04-25'));

        // Add third result
        $well3 = SpecimenWell::buildExample($specimen);
        $r3 = new SpecimenResultQPCR($well3, SpecimenResultQPCR::CONCLUSION_RECOMMENDED);
        $r3->setCreatedAt(new \DateTimeImmutable('2020-04-23'));

        $this->assertSame($r2, $specimen->getMostRecentQPCRResult());
    }

    public function testGetQPCRResultsOrderedByDate()
    {
        $specimen = Specimen::buildExample('C100');

        // Add first result
        $well1 = SpecimenWell::buildExample($specimen);
        $r1 = new SpecimenResultQPCR($well1, SpecimenResultQPCR::CONCLUSION_NEGATIVE);
        $r1->setCreatedAt(new \DateTimeImmutable('2020-04-24'));

        // Add second result (but it's the most recent created at)
        $well2 = SpecimenWell::buildExample($specimen);
        $r2 = new SpecimenResultQPCR($well2, SpecimenResultQPCR::CONCLUSION_POSITIVE);
        $r2->setCreatedAt(new \DateTimeImmutable('2020-04-25'));

        // Add third result
        $well3 = SpecimenWell::buildExample($specimen);
        $r3 = new SpecimenResultQPCR($well3, SpecimenResultQPCR::CONCLUSION_RECOMMENDED);
        $r3->setCreatedAt(new \DateTimeImmutable('2020-04-23'));

        // Most recent
        $found = $specimen->getQPCRResults(1);
        $this->assertCount(1, $found);
        $this->assertEquals([$r2], $found);

        // Two most recent
        $found = $specimen->getQPCRResults(2);
        $this->assertCount(2, $found);
        $this->assertEquals([$r2, $r1], $found);
    }

    public function testGetNewestQPCRResult()
    {
        $specimen = Specimen::buildExample('C100');

        $well1 = SpecimenWell::buildExample($specimen);
        $r1 = new SpecimenResultQPCR($well1, SpecimenResultQPCR::CONCLUSION_NEGATIVE);
        $r1->setCreatedAt(new \DateTimeImmutable('2020-04-24'));

        // R2. This is the latest result
        $well2 = SpecimenWell::buildExample($specimen);
        $r2 = new SpecimenResultQPCR($well2, SpecimenResultQPCR::CONCLUSION_POSITIVE);
        $r2->setCreatedAt(new \DateTimeImmutable('2020-04-25'));

        // R3. Adding an earlier result does not override R2
        $well3 = SpecimenWell::buildExample($specimen);
        $r3 = new SpecimenResultQPCR($well3, SpecimenResultQPCR::CONCLUSION_RECOMMENDED);
        $r3->setCreatedAt(new \DateTimeImmutable('2020-04-23'));

        $this->assertSame($r2, $specimen->getMostRecentQPCRResult());
    }

    public function testGetCliaTestingText()
    {
        $specimen = Specimen::buildExample('C100');

        // Default when Specimen test results not yet available
        $this->assertSame('Awaiting Results', $specimen->getCliaTestingRecommendedText());

        // Add Pending Result
        $well1 = SpecimenWell::buildExample($specimen);
        $r1 = new SpecimenResultQPCR($well1, SpecimenResultQPCR::CONCLUSION_PENDING);
        $this->assertSame('Awaiting Results', $specimen->getCliaTestingRecommendedText());

        // Add Negative Result
        $well2 = SpecimenWell::buildExample($specimen);
        $r2 = new SpecimenResultQPCR($well2, SpecimenResultQPCR::CONCLUSION_NEGATIVE);
        $this->assertSame('No Recommendation', $specimen->getCliaTestingRecommendedText());

        // Add Positive Result
        $well3 = SpecimenWell::buildExample($specimen);
        $r3 = new SpecimenResultQPCR($well3, SpecimenResultQPCR::CONCLUSION_POSITIVE);
        $this->assertSame('Recommend Diagnostic Testing', $specimen->getCliaTestingRecommendedText());

        // Add Recommended Result
        $well4 = SpecimenWell::buildExample($specimen);
        $r4 = new SpecimenResultQPCR($well4, SpecimenResultQPCR::CONCLUSION_RECOMMENDED);
        $this->assertSame('Recommend Diagnostic Testing', $specimen->getCliaTestingRecommendedText());

        // Back to Inconclusive Result
        $well5 = SpecimenWell::buildExample($specimen);
        $r5 = new SpecimenResultQPCR($well5, SpecimenResultQPCR::CONCLUSION_INCONCLUSIVE);
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
        $tube->kioskDropoffComplete($gen, $drop, $group, $tubeType, $collectedAt);

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

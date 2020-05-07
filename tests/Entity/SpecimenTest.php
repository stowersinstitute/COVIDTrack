<?php

namespace App\Tests\Entity;

use App\Entity\DropOff;
use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\SpecimenResultQPCR;
use App\Entity\Tube;
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
        $r1->setCreatedAt(new \DateTime('2020-04-24'));
        $r1->setConclusion(SpecimenResultQPCR::CONCLUSION_NEGATIVE);

        // This is the latest result
        $r2 = new SpecimenResultQPCR($s);
        $r2->setCreatedAt(new \DateTime('2020-04-25'));
        $r2->setConclusion(SpecimenResultQPCR::CONCLUSION_POSITIVE);

        $r3 = new SpecimenResultQPCR($s);
        $r3->setCreatedAt(new \DateTime('2020-04-23'));
        $r3->setConclusion(SpecimenResultQPCR::CONCLUSION_PENDING);

        $this->assertSame($r2, $s->getMostRecentQPCRResult());
    }

    public function testGetCliaTestingText()
    {
        $s = Specimen::buildExample('C100');

        // Default when no results yet
        $this->assertSame('Awaiting Results', $s->getCliaTestingRecommendedText());

        // Pending
        $r1 = new SpecimenResultQPCR($s);
        $r1->setConclusion(SpecimenResultQPCR::CONCLUSION_PENDING);
        $this->assertSame('Awaiting Results', $s->getCliaTestingRecommendedText());

        // Add Negative Result
        $r2 = new SpecimenResultQPCR($s);
        $r2->setConclusion(SpecimenResultQPCR::CONCLUSION_NEGATIVE);
        $this->assertSame('No', $s->getCliaTestingRecommendedText());


        // Add Positive Result
        $r3 = new SpecimenResultQPCR($s);
        $r3->setConclusion(SpecimenResultQPCR::CONCLUSION_POSITIVE);
        $this->assertSame('Yes', $s->getCliaTestingRecommendedText());
    }

    public function testCreateFromTube()
    {
        $tube = new Tube('TUBE-100');

        $drop = new DropOff();
        $group = ParticipantGroup::buildExample('GRP-1');
        $tubeType = Tube::TYPE_BLOOD;
        $collectedAt = new \DateTime('2020-05-20 15:22:44');
        $tube->kioskDropoff($drop, $group, $tubeType, $collectedAt);

        $specimen = Specimen::createFromTube($tube);

        $this->assertSame(Specimen::TYPE_BLOOD, $specimen->getType());
        $this->assertEquals($collectedAt, $specimen->getCollectedAt());
        $this->assertTrue(EntityUtils::isSameEntity($tube->getParticipantGroup(), $specimen->getParticipantGroup()));
    }
}

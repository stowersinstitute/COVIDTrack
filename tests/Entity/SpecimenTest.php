<?php

namespace App\Tests\Entity;

use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\SpecimenResultQPCR;
use PHPUnit\Framework\TestCase;

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
        $this->assertSame('Awaiting Results', $s->getRecommendCliaTestingText());

        // Pending
        $r1 = new SpecimenResultQPCR($s);
        $r1->setConclusion(SpecimenResultQPCR::CONCLUSION_PENDING);
        $this->assertSame('Awaiting Results', $s->getRecommendCliaTestingText());

        // Add Negative Result
        $r2 = new SpecimenResultQPCR($s);
        $r2->setConclusion(SpecimenResultQPCR::CONCLUSION_NEGATIVE);
        $this->assertSame('No', $s->getRecommendCliaTestingText());


        // Add Positive Result
        $r3 = new SpecimenResultQPCR($s);
        $r3->setConclusion(SpecimenResultQPCR::CONCLUSION_POSITIVE);
        $this->assertSame('Yes', $s->getRecommendCliaTestingText());
    }
}

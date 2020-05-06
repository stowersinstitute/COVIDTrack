<?php

namespace App\Tests\Entity;

use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use PHPUnit\Framework\TestCase;

class SpecimenTest extends TestCase
{
    public function testCreateSpecimen()
    {
        $group = new ParticipantGroup('G123', 5);
        $s = new Specimen('CID123', $group);

        $this->assertSame('CID123', $s->getAccessionId());
        $this->assertSame($s->getParticipantGroup(), $group);
    }
}

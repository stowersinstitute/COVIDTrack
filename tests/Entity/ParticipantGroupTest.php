<?php

namespace App\Tests\Entity;

use App\Entity\ParticipantGroup;
use PHPUnit\Framework\TestCase;

class ParticipantGroupTest extends TestCase
{
    public function testCreateGroup()
    {
        $group = new ParticipantGroup('G123', 5);

        $this->assertSame('G123', $group->getAccessionId());
        $this->assertSame(5, $group->getParticipantCount());
        $this->assertCount(0, $group->getSpecimens());
    }

    public function testCreatingGroupRequiresPositiveParticipantCount()
    {
        $this->expectException(\OutOfBoundsException::class);

        new ParticipantGroup('G123', -6);
    }
}

<?php

namespace App\Tests\Entity;

use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\Tube;
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

    public function testAddingSameSpecimenTwiceNotDuplicated()
    {
        $group = new ParticipantGroup('GRP-1', 5);
        $tube = new Tube('T100');
        $specimen = new Specimen('SPEC-100', $group, $tube);

        // Specimen Group properly set
        $this->assertSame($group, $specimen->getParticipantGroup());

        // Group has just 1 Specimen, the one we added
        $groupSpecimens = $group->getSpecimens();
        $this->assertCount(1, $groupSpecimens);

        $groupSpecimen = array_shift($groupSpecimens);
        $this->assertSame($specimen, $groupSpecimen);

        // Add a few more times to test duplicate adding
        $group->addSpecimen($specimen);
        $group->addSpecimen($specimen);
        $group->addSpecimen($specimen);

        // Still has only 1
        $this->assertCount(1, $group->getSpecimens());
    }

    public function testToggleSalivaAcceptStatus()
    {
        $group = new ParticipantGroup('G123', 5);

        // Default
        $this->assertTrue($group->acceptsSalivaSpecimens());

        // Disable
        $group->setAcceptsSalivaSpecimens(false);
        $this->assertFalse($group->acceptsSalivaSpecimens());

        // Re-enable
        $group->setAcceptsSalivaSpecimens(true);
        $this->assertTrue($group->acceptsSalivaSpecimens());
    }

    public function testToggleBloodAcceptStatus()
    {
        $group = new ParticipantGroup('G123', 5);

        // Default
        $this->assertTrue($group->acceptsBloodSpecimens());

        // Disable
        $group->setAcceptsBloodSpecimens(false);
        $this->assertFalse($group->acceptsBloodSpecimens());

        // Re-enable
        $group->setAcceptsBloodSpecimens(true);
        $this->assertTrue($group->acceptsBloodSpecimens());
    }
}

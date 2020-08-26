<?php

namespace App\Tests\Entity;

use App\Entity\DropOffSchedule;
use App\Entity\DropOffWindow;
use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
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

    public function testDeactivatingCancelsDropOffWindows()
    {
        $group = new ParticipantGroup('G100', 5);

        // Ensure in expected state for test purposes
        $this->assertTrue($group->isActive());
        $this->assertCount(0, $group->getDropOffWindows());

        // Add to drop off windows
        $am6 = new \DateTimeImmutable('2020-06-01 6:00am');
        $am8 = new \DateTimeImmutable('2020-06-01 8:00am');
        $am10 = new \DateTimeImmutable('2020-06-01 10:00am');
        $windows = [
            new DropOffWindow(new DropOffSchedule('First'), $am6, $am8),
            new DropOffWindow(new DropOffSchedule('Second'), $am8, $am10),
        ];
        foreach ($windows as $window) {
            $group->addDropOffWindow($window);
        }
        $this->assertCount(count($windows), $group->getDropOffWindows());

        // Deactivate, which should also remove DropOffWindow records
        $group->setIsActive(false);

        // Verify no more drop off windows
        $this->assertFalse($group->isActive());
        $this->assertCount(0, $group->getDropOffWindows());
    }

    public function testCannotAddDropOffWindowsWhenInactive()
    {
        $group = new ParticipantGroup('G100', 5);

        $group->setIsActive(false);

        $am6 = new \DateTimeImmutable('2020-06-01 6:00am');
        $am8 = new \DateTimeImmutable('2020-06-01 8:00am');
        $window = new DropOffWindow(new DropOffSchedule('First'), $am6, $am8);

        // Exception thrown when trying to add DropOffWindow to inactive group
        $this->expectException(\RuntimeException::class);
        $group->addDropOffWindow($window);
    }

    public function testAddingSameSpecimenTwiceNotDuplicated()
    {
        $group = new ParticipantGroup('GRP-1', 5);
        $specimen = new Specimen('SPEC-100', $group);

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

<?php

namespace App\Tests\Entity;

use App\Entity\DropOffSchedule;
use App\Entity\DropOffWindow;
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
}

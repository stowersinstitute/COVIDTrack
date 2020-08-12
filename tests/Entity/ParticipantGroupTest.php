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

    /**
     * @dataProvider provideTitleMatchesIndividualGroupPattern
     */
    public function testTitleMatchesIndividualGroupPattern(string $title, bool $expected)
    {
        $result = ParticipantGroup::titleMatchesIndividualGroupPattern($title);

        $this->assertSame($expected, $result);
    }
    public function provideTitleMatchesIndividualGroupPattern()
    {
        return [
            'Empty' => ['', false],
            'Color' => ['Blue', false],
            '32 characters Color with spaces' => ['Majestic Horizon Silverish Brown', false],
            '32 characters outside hex' => ['abcdefghijklmnopqrstuvwxyzabcdef', false],
            '32 Hex characters lower' => ['09876543210987654321abcdeffedcba', true],
            '32 Hex characters mixed case' => ['09876543210987654321AbCdEfFeDcBa', true],
            '32 Hex characters with spaces' => ['09876543210987 654321AbCdEfFeDcB', false],
        ];
    }
}

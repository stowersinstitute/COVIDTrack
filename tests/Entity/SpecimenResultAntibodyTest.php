<?php

namespace App\Tests\Entity;

use App\Entity\SpecimenResultAntibody;
use PHPUnit\Framework\TestCase;

class SpecimenResultAntibodyTest extends TestCase
{
    /**
     * Tests valid values for signal.
     *
     * @dataProvider provideValidSignal
     */
    public function testValidSignal($input, bool $expected)
    {
        $actual = SpecimenResultAntibody::isValidSignal($input);

        $this->assertSame($expected, $actual);
    }

    public function provideValidSignal()
    {
        return [
            'NULL' => [null, true],
            'Boolean False' => [false, false],
            'Boolean True' => [true, false],
            'String Zero' => ['0', true],
            'String 1' => ['1', true],
            'String 4' => ['4', false],
            'Int 0' => [0, true],
            'Int 1' => [1, true],
            'Int 3' => [3, true],
            'Int 4' => [4, false],
        ];
    }
}
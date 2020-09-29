<?php

namespace App\Tests\Util;

use App\Util\DateUtils;
use PHPUnit\Framework\TestCase;

class DateUtilsTest extends TestCase
{
    /**
     * @dataProvider provideDayFloor
     */
    public function testDayFloor(\DateTimeInterface $inputDate)
    {
        $expected = $inputDate->format("Y-m-d") . ' 00:00:00';

        $modifiedDate = DateUtils::dayFloor($inputDate);

        $this->assertSame($expected, $modifiedDate->format("Y-m-d H:i:s"));
    }

    public function provideDayFloor()
    {
        return [
            'DateTime' => [new \DateTime('2020-09-20 5:00pm')],
            'DateTimeImmutable' => [new \DateTimeImmutable('2020-09-19 5:00pm')],
        ];
    }
    /**
     * @dataProvider provideDayCeil
     */
    public function testDayCeil(\DateTimeInterface $inputDate)
    {
        $expected = $inputDate->format("Y-m-d") . ' 23:59:59';

        $modifiedDate = DateUtils::dayCeil($inputDate);

        $this->assertSame($expected, $modifiedDate->format("Y-m-d H:i:s"));
    }

    public function provideDayCeil()
    {
        return [
            'DateTime' => [new \DateTime('2020-09-20 5:00pm')],
            'DateTimeImmutable' => [new \DateTimeImmutable('2020-09-19 5:00pm')],
        ];
    }
}

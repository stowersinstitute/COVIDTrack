<?php

namespace App\Tests\Entity;

use App\Entity\ExcelImportWorkbook;
use PHPUnit\Framework\TestCase;

class ExcelImportWorkbookTest extends TestCase
{
    /**
     * @dataProvider provideCanGetCellRawValue
     */
    public function testCanGetCellRawValue(string $columnLetter, int $row, $expected)
    {
        $workbook = ExcelImportWorkbook::createFromFilePath(__DIR__.'/workbooks/workbook.xlsx');

        $cellRawValue = $workbook->getFirstWorksheet()->getCellValue($row, $columnLetter);

        if ($expected instanceof \DateTimeInterface) {
            $this->assertInstanceOf(\DateTimeInterface::class, $cellRawValue);

            $format = 'Y-m-d H:i:s';
            $this->assertSame($expected->format($format), $cellRawValue->format($format));
        } else {
            $this->assertSame($expected, $cellRawValue);
        }
    }

    public function provideCanGetCellRawValue()
    {
        return [
            // Strings
            'A2' => ['A', 2, 'SpecimenQPCRResults1'],
            'A3' => ['A', 3, 'SpecimenQPCRResults2'],
            'A4' => ['A', 4, 'SpecimenQPCRResults3'],
            'A5' => ['A', 5, 'SpecimenQPCRResults4'],

            // Dates
            'B2' => ['B', 2, new \DateTime('March 14, 2020 4:20:40pm')],
            'B3' => ['B', 3, new \DateTime('March 14, 2020 5:20:40pm')],
            'B4' => ['B', 4, new \DateTime('March 14, 2020 6:20:40pm')],
            'B5' => ['B', 5, new \DateTime('March 14, 2020 7:20:40pm')],
        ];
    }
}

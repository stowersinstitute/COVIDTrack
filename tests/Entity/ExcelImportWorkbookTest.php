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

            // Numbers
            'C2' => ['C', 2, '20.439682831841'],
            'C3' => ['C', 3, '18.893144729853'],
            'C4' => ['C', 4, '19.621005213174'],
            'C5' => ['C', 5, '21.98765432'],
        ];
    }

    /**
     * @dataProvider provideCanGetCellTextValue
     */
    public function testCanGetCellTextValue(string $columnLetter, int $row, $expected)
    {
        $workbook = ExcelImportWorkbook::createFromFilePath(__DIR__.'/workbooks/workbook.xlsx');

        $cellRawValue = $workbook->getFirstWorksheet()->getCellTextValue($row, $columnLetter);

        $this->assertSame($expected, $cellRawValue);
    }

    public function provideCanGetCellTextValue()
    {
        return [
            // Strings
            'A2' => ['A', 2, 'SpecimenQPCRResults1'],
            'A3' => ['A', 3, 'SpecimenQPCRResults2'],
            'A4' => ['A', 4, 'SpecimenQPCRResults3'],
            'A5' => ['A', 5, 'SpecimenQPCRResults4'],

            // Dates
            'B2' => ['B', 2, '14-Mar-20'],
            'B3' => ['B', 3, '3/14/20 5:20 PM'],
            'B4' => ['B', 4, '3/14/2020'],
            'B5' => ['B', 5, '14-Mar-2020'],

            // Numbers
            'C2' => ['C', 2, '20.43968283'],
            'C3' => ['C', 3, '18.89314473'],
            'C4' => ['C', 4, '19.62100521'],
            'C5' => ['C', 5, '21.98765432'],
        ];
    }
}

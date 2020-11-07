<?php

namespace App\Entity;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * A cell within an ExcelImportWorksheet
 */
class ExcelImportCell
{
    const VALUE_TYPE_SCALAR     = 'SCALAR';
    const VALUE_TYPE_DATETIME   = 'DATETIME';
    const VALUE_TYPE_BOOLEAN    = 'BOOLEAN';

    const VALUE_BOOLEAN_TRUE = 'B_TRUE';
    const VALUE_BOOLEAN_FALSE = 'B_FALSE';
    const VALUE_BOOLEAN_NULL = 'B_NULL';

    /**
     * @var int 1-based row number (matches the row displayed in Excel)
     */
    protected $rowIndex;

    /**
     * @var string Column label from Excel, eg. "A"
     */
    protected $colIndex;

    /**
     * @var string Cell value
     * @see setValueFromExcelCell
     */
    protected $value;

    /**
     * One of:
     *  VALUE_TYPE_SCALAR       a string, integer, etc.
     *  VALUE_TYPE_DATETIME     a native PHP \DateTimeImmutable object
     *
     * @var string
     * @see setValueFromExcelCell
     */
    protected $valueType;

    public function __construct(ExcelImportWorksheet $worksheet)
    {
        $this->valueType = self::VALUE_TYPE_SCALAR;
        $worksheet->addCell($this);
    }

    /**
     * Sets the value of this cell to match what's in the Excel cell
     */
    public function setValueFromExcelCell(Cell $cell)
    {
        // Typical value is just the string displayed in Excel
        $internalDataType = self::VALUE_TYPE_SCALAR;
        $storeValue = $cell->getFormattedValue();

        // Resolve formulas
        // todo: not actually sure what happens when a formula resolves to a date...
        if ($cell->getDataType() === DataType::TYPE_FORMULA) {
            $storeValue = $cell->getCalculatedValue();
        }

        // Convert dates to native PHP format
        if (Date::isDateTime($cell)) {
            $internalDataType = self::VALUE_TYPE_DATETIME;
            $storeValue = Date::excelToDateTimeObject($cell->getValue());
            $storeValue = $storeValue->format(DATE_ATOM);
        }

        // Convert any number to its string equivalent
        if (is_int($storeValue) || is_float(($storeValue))) {
            $storeValue = (string) $storeValue;
        }

        // Trim whitespace
        if (is_string($storeValue)) {
            $storeValue = trim($storeValue);
        }

        // Boolean True
        if (
            $cell->getDataType() === "b"
            && ($cell->getValue() === true || $cell->getValue() === false)
        ) {
            $internalDataType = self::VALUE_TYPE_BOOLEAN;

            $storeValue = self::VALUE_BOOLEAN_NULL;
            if ($cell->getValue() === true) {
                $storeValue = self::VALUE_BOOLEAN_TRUE;
            } else if ($cell->getValue() === false) {
                $storeValue = self::VALUE_BOOLEAN_FALSE;
            }
        }

        $this->valueType = $internalDataType;
        $this->value = $storeValue;
    }

    /**
     * @return string|\DateTimeImmutable|bool
     */
    public function getValue()
    {
        if ($this->valueType === self::VALUE_TYPE_DATETIME) {
            return \DateTimeImmutable::createFromFormat(DATE_ATOM, $this->value);
        }

        if ($this->valueType === self::VALUE_TYPE_BOOLEAN) {
            switch ($this->value) {
                case self::VALUE_BOOLEAN_TRUE:
                    return true;
                case self::VALUE_BOOLEAN_FALSE:
                    return false;
                case self::VALUE_BOOLEAN_NULL:
                    return null;
            }

            throw new \RuntimeException('Unrecognized BOOLEAN value');
        }

        return $this->value;
    }

    public function getRowIndex(): int
    {
        return $this->rowIndex;
    }

    public function setRowIndex(int $rowIndex): void
    {
        $this->rowIndex = $rowIndex;
    }

    public function getColIndex(): string
    {
        return $this->colIndex;
    }

    public function setColIndex(string $colIndex): void
    {
        $this->colIndex = $colIndex;
    }

    /**
     * @deprecated Not really deprecated. You probably want setValueFromExcelCell()
     * @internal
     */
    public function setValue($value): void
    {
        $this->value = $value;
    }
}

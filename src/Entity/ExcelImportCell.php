<?php


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * @ORM\Entity
 */
class ExcelImportCell
{
    const VALUE_TYPE_SCALAR     = 'SCALAR';
    const VALUE_TYPE_DATETIME   = 'DATETIME';

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var int 1-based row number (matches the row displayed in Excel)
     *
     * @ORM\Column(name="rowIndex", type="integer", nullable=false)
     */
    protected $rowIndex;

    /**
     * @var string Column label from Excel, eg. "A"
     *
     * @ORM\Column(name="colIndex", type="string", length=255, nullable=false)
     */
    protected $colIndex;

    /**
     * @var string Cell value
     *
     * @ORM\Column(name="value", type="string", length=255, nullable=true)
     */
    protected $value;

    /**
     * One of:
     *  VALUE_TYPE_SCALAR       a string, integer, etc.
     *  VALUE_TYPE_DATETIME     a native PHP \DateTimeImmutable object
     *
     * @var string
     *
     * @ORM\Column(name="valueType", type="string", length=255, nullable=true)
     */
    protected $valueType;

    /**
     * @var ExcelImportWorksheet Worksheet this cell belongs to
     *
     * @ORM\ManyToOne(targetEntity="ExcelImportWorksheet", inversedBy="cells")
     * @ORM\JoinColumn(name="worksheetId", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected $worksheet;

    public function __construct(ExcelImportWorksheet $worksheet)
    {
        $this->worksheet = $worksheet;
        $this->worksheet->addCell($this);

        $this->valueType = self::VALUE_TYPE_SCALAR;
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

        $this->valueType = $internalDataType;
        $this->value = $storeValue;
    }

    /**
     * @return string|\DateTimeImmutable
     */
    public function getValue()
    {
        if ($this->valueType === self::VALUE_TYPE_DATETIME) {
            return \DateTimeImmutable::createFromFormat(DATE_ATOM, $this->value);
        }

        return $this->value;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getRowIndex(): int
    {
        return $this->rowIndex;
    }

    /**
     * @param int $rowIndex
     */
    public function setRowIndex(int $rowIndex): void
    {
        $this->rowIndex = $rowIndex;
    }

    /**
     * @return string
     */
    public function getColIndex(): string
    {
        return $this->colIndex;
    }

    /**
     * @param string $colIndex
     */
    public function setColIndex(string $colIndex): void
    {
        $this->colIndex = $colIndex;
    }

    /**
     * @param string $value
     */
    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    /**
     * @return ExcelImportWorksheet
     */
    public function getWorksheet(): ExcelImportWorksheet
    {
        return $this->worksheet;
    }

    /**
     * @param ExcelImportWorksheet $worksheet
     */
    public function setWorksheet(ExcelImportWorksheet $worksheet): void
    {
        $this->worksheet = $worksheet;
    }
}
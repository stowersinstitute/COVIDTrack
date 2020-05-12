<?php


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * A cell within an ExcelImportWorksheet
 *
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
     * @see setValueFromExcelCell
     *
     * @ORM\Column(name="value", type="text", nullable=true)
     */
    protected $value;

    /**
     * One of:
     *  VALUE_TYPE_SCALAR       a string, integer, etc.
     *  VALUE_TYPE_DATETIME     a native PHP \DateTimeImmutable object
     *
     * @var string
     * @see setValueFromExcelCell
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
        $storeValue = trim($cell->getFormattedValue());

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

    public function getId(): ?int
    {
        return $this->id;
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
     * @deprecated You probably want setValueFromExcelCell
     */
    public function setValue($value): void
    {
        $this->value = $value;
    }

    public function getWorksheet(): ExcelImportWorksheet
    {
        return $this->worksheet;
    }

    public function setWorksheet(ExcelImportWorksheet $worksheet): void
    {
        $this->worksheet = $worksheet;
    }
}
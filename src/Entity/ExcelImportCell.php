<?php


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class ExcelImportCell
{
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
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
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
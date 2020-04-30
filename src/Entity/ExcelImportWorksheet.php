<?php


namespace App\Entity;


use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class ExcelImportWorksheet
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
     * @var string Name of the worksheet tab in the workbook
     *
     * @ORM\Column(name="name", type="string", length=255, nullable=true)
     */
    protected $name;

    /**
     * @var ExcelImportWorkbook The workbook this sheet belongs to
     *
     * @ORM\ManyToOne(targetEntity="ExcelImportWorkbook", inversedBy="worksheets")
     * @ORM\JoinColumn(name="workbookId", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected $workbook;

    /**
     * @var ExcelImportCell[] A cell within the worksheet
     *
     * @ORM\OneToMany(targetEntity="ExcelImportCell", cascade={"persist", "remove"}, orphanRemoval=true, mappedBy="worksheet")
     * @ORM\JoinColumn(name="cells", referencedColumnName="uid")
     * @ORM\OrderBy({"rowIndex" = "ASC"})
     */
    protected $cells;

    public function __construct(ExcelImportWorkbook $workbook)
    {
        $this->workbook = $workbook;
        $this->workbook->addWorksheet($this);

        $this->cells = new ArrayCollection();
    }

    public function getNumRows()
    {
        $maxRowIndex = 0;
        foreach ($this->cells as $cell) {
            $maxRowIndex = max($maxRowIndex, $cell->getRowIndex());
        }

        return $maxRowIndex;
    }

    public function getRowIndexes() : array
    {
        $rowIndexes = [];
        foreach ($this->cells as $cell) {
            $rowIndexes[] = $cell->getRowIndex();
        }

        return array_unique($rowIndexes);
    }

    public function getCellValue($rowIndex, $column)
    {
        $cell = $this->getCell($rowIndex, $column);
        if (!$cell) return null;

        return $cell->getValue();
    }

    public function getCell($rowIndex, $column) : ExcelImportCell
    {
        foreach ($this->cells as $cell) {
            if ($cell->getRowIndex() === $rowIndex && $cell->getColIndex() === $column) {
                return $cell;
            }
        }

        return null;
    }

    public function addCell(ExcelImportCell $cell)
    {
        $cell->setWorksheet($this);
        $this->cells->add($cell);
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
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return ExcelImportWorkbook
     */
    public function getWorkbook(): ExcelImportWorkbook
    {
        return $this->workbook;
    }

    /**
     * @param ExcelImportWorkbook $workbook
     */
    public function setWorkbook(ExcelImportWorkbook $workbook): void
    {
        $this->workbook = $workbook;
    }

    /**
     * @return ExcelImportCell[]
     */
    public function getCells(): array
    {
        return $this->cells;
    }

    /**
     * @param ExcelImportCell[] $cells
     */
    public function setCells(array $cells): void
    {
        $this->cells = $cells;
    }
}
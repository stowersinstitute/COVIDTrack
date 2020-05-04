<?php


namespace App\Entity;


use App\Util\EntityUtils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents an excel worksheet associated with a workbook
 *
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
     * @var string Title of the worksheet tab in the workbook
     *
     * @ORM\Column(name="title", type="string", length=255)
     */
    protected $title;

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

    public function __construct(ExcelImportWorkbook $workbook, $title)
    {
        $this->workbook = $workbook;
        $this->workbook->addWorksheet($this);

        $this->title = $title;

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

    /**
     * @return \DateTimeImmutable|string|null
     */
    public function getCellValue($rowIndex, $column)
    {
        $cell = $this->getCell($rowIndex, $column);
        if (!$cell) return null;

        return $cell->getValue();
    }

    public function getCell($rowIndex, $column) : ?ExcelImportCell
    {
        foreach ($this->cells as $cell) {
            if ($cell->getRowIndex() === $rowIndex && $cell->getColIndex() === $column) {
                return $cell;
            }
        }

        return null;
    }

    /**
     * @return ExcelImportCell[]
     */
    public function getCells(): array
    {
        return $this->cells;
    }

    public function addCell(ExcelImportCell $cell)
    {
        $cell->setWorksheet($this);
        if (!$this->hasCell($cell)) {
            $this->cells->add($cell);
        }
    }

    public function hasCell(ExcelImportCell $cell) : bool
    {
        foreach ($this->cells as $currCell) {
            if (EntityUtils::isSameEntity($currCell, $cell)) return true;
        }

        return false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getWorkbook(): ExcelImportWorkbook
    {
        return $this->workbook;
    }

    public function setWorkbook(ExcelImportWorkbook $workbook): void
    {
        $this->workbook = $workbook;
    }
}
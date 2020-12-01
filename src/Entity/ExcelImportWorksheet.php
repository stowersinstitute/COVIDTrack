<?php

namespace App\Entity;

use App\Util\EntityUtils;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Represents an excel worksheet associated with a workbook
 */
class ExcelImportWorksheet
{
    /**
     * @var string Title of the worksheet tab in the workbook
     */
    protected $title;

    /**
     * @var ExcelImportCell[] A cell within the worksheet
     */
    protected $cells;

    public function __construct(ExcelImportWorkbook $workbook, $title)
    {
        $this->title = $title;

        $this->cells = new ArrayCollection();

        $workbook->addWorksheet($this);
    }

    public function getId(): int
    {
        return spl_object_id($this);
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
    public function getCellValue(int $rowIndex, string $column)
    {
        $cell = $this->getCell($rowIndex, $column);
        if (!$cell) return null;

        return $cell->getValue();
    }

    public function getCell(int $rowIndex, string $column) : ?ExcelImportCell
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
        return $this->cells->getValues();
    }

    public function addCell(ExcelImportCell $cell)
    {
        $this->cells->add($cell);
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}

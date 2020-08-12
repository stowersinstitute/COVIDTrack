<?php

namespace App\ExcelImport;

use App\Entity\AppUser;
use App\Entity\ExcelImportCell;
use App\Entity\ExcelImportWorkbook;
use App\Entity\ExcelImportWorksheet;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Parent class for managing Excel imports
 *
 * ## Usage
 *
 * 1. Extend this class
 *
 * 2. Override process() to read from $this->worksheet and write to $this->output
 *      - If necessary, override getNumImportedItems() to reflect what is stored in $this->output
 */
abstract class BaseExcelImporter
{
    /** @var ExcelImportWorksheet */
    protected $worksheet;

    /**
     * @var int Row to start import on
     */
    protected $startingRow = 2; // header is typically row 1

    /**
     * Reserved for future use CVDLS-45
     * @var array
     */
    protected $columnMap = [];

    /** @var ImportMessage[] */
    protected $messages = [];

    /**
     * @var mixed Result of this import
     */
    protected $output;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * Parse what's in the excel file and populate $this->output
     *
     * @param bool $commit If true, changes should be committed to the database
     */
    abstract public function process($commit = false);


    public function __construct(ExcelImportWorksheet $worksheet)
    {
        $this->worksheet = $worksheet;
    }

    public static function createSpreadsheetFromPath(string $filepath): Spreadsheet
    {
        return IOFactory::load($filepath);
    }

    /**
     * Given UploadedFile, parse it into an ExcelImportWorkbook.
     *
     * @param UploadedFile $file
     * @param AppUser|null $uploadedByUser
     * @return ExcelImportWorkbook
     */
    public static function createExcelImportWorkbookFromUpload(UploadedFile $file, AppUser $uploadedByUser): ExcelImportWorkbook
    {
        $filepath = $file->getRealPath();
        $spreadsheet = static::createSpreadsheetFromPath($filepath);

        $importWorkbook = new ExcelImportWorkbook();
        $importWorkbook->setFilename($file->getClientOriginalName());
        $importWorkbook->setFileMimeType($file->getMimeType());
        $importWorkbook->setUploadedAt(new \DateTimeImmutable());
        $importWorkbook->setUploadedBy($uploadedByUser);

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $importWorksheet = new ExcelImportWorksheet($importWorkbook, $sheet->getTitle());

            foreach ($sheet->getRowIterator() as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $importCell = new ExcelImportCell($importWorksheet);
                    $importCell->setRowIndex($row->getRowIndex());
                    $importCell->setColIndex($cell->getColumn());

                    $importCell->setValueFromExcelCell($cell);
                }
            }
        }

        return $importWorkbook;
    }

    /**
     * Get all cell values for a specific column letter.
     *
     * @param Worksheet $worksheet
     * @param string    $columnLetter Example: "A"
     * @param int       $startAtRow   Default 2 assumes Row 1 is header text
     * @return array<int, string> Keys are int $rowNumber, Values are string $cellValue
     */
    public static function getColumnValues(Worksheet $worksheet, string $columnLetter, int $startAtRow = 2): array
    {
        $max = $worksheet->getHighestRow($columnLetter);

        $values = [];
        for ($rowNumber = $startAtRow; $rowNumber <= $max; $rowNumber++) {
            $columnIdx = Coordinate::columnIndexFromString($columnLetter);

            $cell = $worksheet->getCellByColumnAndRow($columnIdx, $rowNumber);
            if (!$cell) {
                throw new \RuntimeException(sprintf('Cannot find Cell for Column %s Row %d', $columnLetter, $rowNumber));
            }

            $rawValue = trim($cell->getValue());
            if (null !== $rawValue) {
                $values[$rowNumber] = $rawValue;
            }
        }

        return $values;
    }

    /**
     * Get filename of Excel document being imported.
     */
    public function getFilename(): ?string
    {
        if (!$this->worksheet) {
            return null;
        }

        return $this->worksheet->getWorkbook()->getFilename();
    }

    /**
     * Convenience method to help skip empty rows in Excel since these are usually
     * leftovers from copying and pasting into an existing template and should not
     * be considered validation errors
     */
    public function rowDataBlank($rowNumber) : bool
    {
        foreach ($this->columnMap as $columnId => $columnLetter) {
            $value = $this->worksheet->getCellValue($rowNumber, $columnLetter);

            // Anything with a string length > 0 should be considered to have data
            if (strlen($value) > 0) return false;
        }

        return true;
    }

    public function hasErrors() : bool
    {
        foreach ($this->messages as $message) {
            if ($message->isError()) return true;
        }

        return false;
    }

    public function hasNonErrors() : bool
    {
        foreach ($this->messages as $message) {
            if ($message->isError() === false) return true;
        }

        return false;
    }

    /**
     * @return ImportMessage[]
     */
    public function getErrors(): array
    {
        return array_filter($this->messages, function(ImportMessage $m) {
            return $m->isError();
        });
    }

    /**
     * @return ImportMessage[]
     */
    public function getNonErrors(): array
    {
        return array_filter($this->messages, function(ImportMessage $m) {
            return $m->isError() === false;
        });
    }

    /**
     * @return ImportMessage[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @return mixed
     */
    public function getOutput()
    {
        // Run import logic if it hasn't happened already
        if ($this->output === null) {
            $this->process();
        }

        return $this->output;
    }

    public function getNumImportedItems() : int
    {
        if (!is_array($this->output)) throw new \InvalidArgumentException('output must be an array to call this method');

        return count($this->output);
    }

    public function getSourceLabel() : string
    {
        return $this->worksheet->getWorkbook()->getFilename();
    }

    public function getWorksheetTitle() : string
    {
        return $this->worksheet->getTitle();
    }

    public function getEntityManager(): ?EntityManagerInterface
    {
        return $this->em;
    }

    public function setEntityManager(?EntityManagerInterface $em): void
    {
        $this->em = $em;
    }

    /**
     * @return int The 1-based starting row number (matches what you see in Excel)
     */
    public function getStartingRow(): int
    {
        return $this->startingRow;
    }
}
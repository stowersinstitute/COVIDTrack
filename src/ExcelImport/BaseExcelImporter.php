<?php


namespace App\ExcelImport;


use App\Entity\ExcelImportWorksheet;
use Doctrine\ORM\EntityManager;

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
     * @var EntityManager
     */
    protected $em;

    /**
     * Parse what's in the excel file and populate $this->output
     */
    abstract public function process();


    public function __construct(ExcelImportWorksheet $worksheet)
    {
        $this->worksheet = $worksheet;
    }

    public function hasErrors() : bool
    {
        foreach ($this->messages as $message) {
            if ($message->isError()) return true;
        }

        return false;
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

    public function getEntityManager(): ?EntityManager
    {
        return $this->em;
    }

    public function setEntityManager(?EntityManager $em): void
    {
        $this->em = $em;
    }
}
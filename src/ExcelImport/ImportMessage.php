<?php


namespace App\ExcelImport;


/**
 * Messages generated while processing an Excel file
 *
 * If any messages have $isError = true the import cannot continue
 */
class ImportMessage
{
    /**
     * @var int 1-based row number, matches up with value displayed in Excel
     */
    protected $rowNumber;

    /**
     * @var string A-Z column letter as it appears in Excel
     */
    protected $columnLetter;

    /**
     * @var string details to display to the user
     */
    protected $details;

    /**
     * @var bool If true, this is something that prevents the import from continuing
     */
    protected $isError = false;

    public static function newError(string $message, $rowNumber = null, $columnLetter = null)
    {
        $object = new static();
        $object->setIsError(true);

        $object->setDetails($message);
        $object->setRowNumber($rowNumber);
        $object->setColumnLetter($columnLetter);

        return $object;
    }

    /**
     * @return int
     */
    public function getRowNumber(): ?int
    {
        return $this->rowNumber;
    }

    public function setRowNumber(?int $rowNumber): void
    {
        $this->rowNumber = $rowNumber;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function setDetails(?string $details): void
    {
        $this->details = $details;
    }

    public function isError(): bool
    {
        return $this->isError;
    }

    public function setIsError(bool $isError): void
    {
        $this->isError = $isError;
    }

    public function getColumnLetter(): ?string
    {
        return $this->columnLetter;
    }

    public function setColumnLetter(?string $columnLetter): void
    {
        $this->columnLetter = $columnLetter;
    }
}
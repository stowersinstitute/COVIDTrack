<?php


namespace App\ExcelImport;


use App\Entity\AppUser;
use App\Entity\ExcelImportCell;
use App\Entity\ExcelImportWorkbook;
use App\Entity\ExcelImportWorksheet;
use App\Util\EntityUtils;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Security;

class ExcelImporter
{
    /** @var Security  */
    protected $security;

    public function __construct(Security $security = null)
    {
        $this->security = $security;
    }

    public function createWorkbookFromUpload(UploadedFile $file) : ExcelImportWorkbook
    {
        $reader = new Xlsx();
        $spreadsheet = $reader->load($file->getRealPath());

        $importWorkbook = new ExcelImportWorkbook();
        $importWorkbook->setFilename($file->getClientOriginalName());
        $importWorkbook->setUploadedAt(new \DateTimeImmutable());

        if ($this->security) $importWorkbook->setUploadedBy($this->security->getUser());

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
     * Returns true if $actor has permission to view and import $workbook
     *
     * If $actor is null the logged in user is used
     */
    public function userMustHavePermissions(ExcelImportWorkbook $workbook, AppUser $actor = null) : bool
    {
        if (!$this->security && !$actor) {
            throw new \InvalidArgumentException('Cannot check permissions: $actor must be specified or a user must be logged in');
        }

        // Default to checking against the logged in user
        if (!$actor) $actor = $this->security->getUser();

        return EntityUtils::isSameEntity($actor, $workbook->getUploadedBy());
    }
}
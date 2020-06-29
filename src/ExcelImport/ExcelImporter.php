<?php


namespace App\ExcelImport;


use App\Entity\AppUser;
use App\Entity\ExcelImportCell;
use App\Entity\ExcelImportWorkbook;
use App\Entity\ExcelImportWorksheet;
use App\Util\EntityUtils;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
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
        $importWorkbook = ExcelImportWorkbook::createFromFilePath($file->getRealPath());
        $importWorkbook->setFilename($file->getClientOriginalName());

        if ($this->security) $importWorkbook->setUploadedBy($this->security->getUser());

        return $importWorkbook;
    }

    /**
     * Returns true if $actor has permission to view and import $workbook
     *
     * If $actor is null the logged in user is used
     */
    public function userMustHavePermissions(ExcelImportWorkbook $workbook, AppUser $actor = null)
    {
        if (!$this->security && !$actor) {
            throw new AccessDeniedException('Cannot check permissions: $actor must be specified or a user must be logged in');
        }

        // Default to checking against the logged in user
        if (!$actor) $actor = $this->security->getUser();

        if (!EntityUtils::isSameEntity($actor, $workbook->getUploadedBy())) {
            throw new AccessDeniedException('You do not have permission to access this import');
        }
    }
}
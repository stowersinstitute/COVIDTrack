<?php


namespace App\Entity;


use App\Util\EntityUtils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * A workbook created by parsing an uploaded Excel file
 *
 * @ORM\Entity(repositoryClass="App\Entity\ExcelImportWorkbookRepository")
 * @ORM\Table(name="excel_import_workbooks")
 */
class ExcelImportWorkbook
{
    /**
     * @var int
     * @ORM\Id()
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * Filename provided by the user when the file was uploaded
     *
     * @var string
     * @ORM\Column(name="filename", type="string", length=255, nullable=true)
     */
    protected $filename;

    /**
     * File MIME-type provided by uploaded file
     *
     * @var string
     * @ORM\Column(name="file_mime_type", type="string", length=255, nullable=true)
     */
    protected $fileMimeType;

    /**
     * When the file was uploaded
     * @var \DateTimeImmutable
     *
     * @ORM\Column(name="uploaded_at", type="datetime_immutable", nullable=true)
     */
    protected $uploadedAt;

    /**
     * @var AppUser The user who uploaded this file
     *
     * @ORM\ManyToOne(targetEntity="AppUser")
     * @ORM\JoinColumn(name="uploaded_by_id", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     */
    protected $uploadedBy;

    /**
     * Worksheets associated with this workbook
     * @var ArrayCollection|ExcelImportWorksheet[]
     * @ORM\Column(name="worksheets", type="array", nullable=true)
     */
    protected $worksheets;

    public function __construct()
    {
        $this->uploadedAt = new \DateTimeImmutable();
        $this->worksheets = new ArrayCollection();
    }

    /**
     * @param string $path Path to Excel XLS/XLSX/CSV workbook
     */
    public static function createFromFilePath(string $path): ExcelImportWorkbook
    {
        $spreadsheet = IOFactory::load($path);
        $filename = basename($path);

        $importWorkbook = new ExcelImportWorkbook();
        $importWorkbook->setFilename($filename);
        $importWorkbook->setUploadedAt(new \DateTimeImmutable());

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

    public function getFirstWorksheet() : ?ExcelImportWorksheet
    {
        return $this->worksheets->first();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): void
    {
        $this->filename = $filename;
    }

    public function getFileMimeType(): ?string
    {
        return $this->fileMimeType;
    }

    public function setFileMimeType(?string $mime): void
    {
        $this->fileMimeType = $mime;
    }

    public function getUploadedAt(): ?\DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(?\DateTimeImmutable $uploadedAt): void
    {
        $this->uploadedAt = $uploadedAt;
    }

    public function addWorksheet(ExcelImportWorksheet $worksheet)
    {
        if ($this->hasWorksheet($worksheet)) return;

        $this->worksheets->add($worksheet);
    }

    public function hasWorksheet(ExcelImportWorksheet $worksheet) : bool
    {
        foreach ($this->worksheets as $currWorksheet) {
            if (EntityUtils::isSameEntity($currWorksheet, $worksheet)) return true;
        }

        return false;
    }

    /**
     * @return ExcelImportWorksheet[]
     */
    public function getWorksheets(): array
    {
        return $this->worksheets->getValues();
    }

    public function getUploadedBy(): ?AppUser
    {
        return $this->uploadedBy;
    }

    public function setUploadedBy(?AppUser $uploadedBy): void
    {
        $this->uploadedBy = $uploadedBy;
    }
}
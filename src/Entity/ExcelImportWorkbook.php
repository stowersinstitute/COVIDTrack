<?php


namespace App\Entity;


use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @ORM\Entity
 */
class ExcelImportWorkbook
{
    /**
     * @var int
     * @ORM\Id()
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * Filename provided by the user when the file was uploaded
     * @var string
     *
     * @ORM\Column(name="filename", type="string", length=255, nullable=true)
     */
    protected $filename;

    /**
     * When the file was uploaded
     * @var \DateTime
     *
     * @ORM\Column(name="uploadedAt", type="datetime", nullable=true)
     */
    protected $uploadedAt;

    /**
     * Worksheets associated with this workbook
     * @var ExcelImportWorksheet[]
     *
     * @ORM\OneToMany(targetEntity="ExcelImportWorksheet", cascade={"persist", "remove"}, orphanRemoval=true, mappedBy="workbook")
     * @ORM\JoinColumn(name="worksheets", referencedColumnName="workbookId")
     */
    protected $worksheets;

    /**
     * Populates an ExcelImportWorkbook from data contained within an uploaded file
     */
    public static function createFromUpload(UploadedFile $file) : ExcelImportWorkbook
    {
        $reader = new Xlsx();
        $spreadsheet = $reader->load($file->getRealPath());

        $importWorkbook = new ExcelImportWorkbook();
        $importWorkbook->setFilename($file->getClientOriginalName());

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $importWorksheet = new ExcelImportWorksheet($importWorkbook);
            $importWorksheet->setName($sheet->getTitle());

            foreach ($sheet->getRowIterator() as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $importCell = new ExcelImportCell($importWorksheet);
                    $importCell->setRowIndex($row->getRowIndex());
                    $importCell->setColIndex($cell->getColumn());

                    $importCell->setValue($cell->getFormattedValue());
                }
            }
        }

        return $importWorkbook;
    }

    public function __construct()
    {
        $this->uploadedAt = new \DateTime();
        $this->worksheets = new ArrayCollection();
    }

    public function getFirstWorksheet() : ?ExcelImportWorksheet
    {
        return $this->worksheets->first();
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
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * @param string $filename
     */
    public function setFilename(string $filename): void
    {
        $this->filename = $filename;
    }

    /**
     * @return \DateTime
     */
    public function getUploadedAt(): \DateTime
    {
        return $this->uploadedAt;
    }

    /**
     * @param \DateTime $uploadedAt
     */
    public function setUploadedAt(\DateTime $uploadedAt): void
    {
        $this->uploadedAt = $uploadedAt;
    }

    public function addWorksheet(ExcelImportWorksheet $worksheet)
    {
        $this->worksheets->add($worksheet);
        $worksheet->setWorkbook($this);
    }
}
<?php


namespace App\Entity;


use App\Util\EntityUtils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * A workbook created by parsing an uploaded Excel file
 *
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
     * @var \DateTimeImmutable
     *
     * @ORM\Column(name="uploadedAt", type="datetime_immutable", nullable=true)
     */
    protected $uploadedAt;

    /**
     * @var AppUser The user who uploaded this file
     *
     * @ORM\ManyToOne(targetEntity="AppUser")
     * @ORM\JoinColumn(name="uploadedById", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     */
    protected $uploadedBy;

    /**
     * Worksheets associated with this workbook
     * @var ExcelImportWorksheet[]
     *
     * @ORM\OneToMany(targetEntity="ExcelImportWorksheet", cascade={"persist", "remove"}, orphanRemoval=true, mappedBy="workbook")
     * @ORM\JoinColumn(name="worksheets", referencedColumnName="workbookId")
     */
    protected $worksheets;

    public function __construct()
    {
        $this->uploadedAt = new \DateTimeImmutable();
        $this->worksheets = new ArrayCollection();
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
        $worksheet->setWorkbook($this);
    }

    public function hasWorksheet(ExcelImportWorksheet $worksheet) : bool
    {
        foreach ($this->worksheets as $currWorksheet) {
            if (EntityUtils::isSameEntity($currWorksheet, $worksheet)) return true;
        }

        return false;
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
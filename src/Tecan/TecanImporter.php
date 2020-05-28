<?php

namespace App\Tecan;

use App\Entity\AppUser;
use App\Entity\ExcelImportCell;
use App\Entity\ExcelImportWorkbook;
use App\Entity\ExcelImportWorksheet;
use App\Entity\Tube;
use App\Entity\WellPlate;
use App\ExcelImport\BaseExcelImporter;
use App\ExcelImport\TecanOutputReader;
use App\Repository\TubeRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\BaseReader;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class TecanImporter extends BaseExcelImporter
{
    const STARTING_ROW = 3;

    const COLUMN_WELL_POSITION = 'A';
    const COLUMN_TUBE_ACCESSION_ID = 'F';

    /**
     * @var TubeRepository
     */
    private $tubeRepo;

    /**
     * Cache of found Tubes used instead of query caching
     *
     * @var array Keys Tube.accessionId; Values Tube entity
     */
    private $seenTubes = [];

    public function __construct(EntityManagerInterface $em, ExcelImportWorksheet $worksheet)
    {
        $this->setEntityManager($em);
        $this->tubeRepo = $em->getRepository(Tube::class);

        parent::__construct($worksheet);

        $this->startingRow = self::STARTING_ROW; // Tecan output has 2 rows of header info

        $this->columnMap = [
            'wellPosition' => self::COLUMN_WELL_POSITION, // 1-96 for 96-well position
            'tubeAccessionId' => self::COLUMN_TUBE_ACCESSION_ID,
        ];
    }

    /**
     * Returns true if there is at least one group associated with $action
     *
     * $action can be:
     *  - created
     *  - updated
     *  - deactivated
     *
     * See process()
     */
    public function hasGroupsForAction($action) : bool
    {
        if (!isset($this->output[$action])) {
            return false;
        }

        return count($this->output[$action]) > 0;
    }

    public static function createSpreadsheetFromPath(string $filepath): Spreadsheet
    {
        $getReaderForFilepath = function(string $filepath): BaseReader {
            $possibleReaders = [
                new Csv(),
                new TecanOutputReader(), // Tab-delimited
            ];

            foreach ($possibleReaders as $reader) {
                if ($reader->canRead($filepath)) {
                    return $reader;
                }
            }

            throw new \RuntimeException('Cannot find spreadsheet reader capable of parsing file');
        };

        $reader = $getReaderForFilepath($filepath);

        return $reader->load($filepath);
    }

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
     * OVERRIDDEN to match format in process()
     */
    public function getNumImportedItems(): int
    {
        if ($this->output === null) throw new \LogicException('Called getNumImportedItems before process()');

        $changedItems = 0;
        foreach ($this->output as $action => $groups) {
            $changedItems += count($groups);
        }

        return $changedItems;
    }

    /**
     * Returns the raw value of cells in the column containing Tube Accession IDs.
     * These values are not guaranteed to be valid Tubes. They are only what is
     * contained in the file.
     *
     * @param string $filepath Full path to the file
     * @return string[]
     */
    public static function getRawTubeAccessionIds(string $filepath): array
    {
        $spreadsheet = static::createSpreadsheetFromPath($filepath);
        $worksheet = $spreadsheet->getActiveSheet();

        $max = $worksheet->getHighestRow(self::COLUMN_TUBE_ACCESSION_ID);

        $tubeAccessionIds = [];
        for ($rowNumber = static::STARTING_ROW; $rowNumber <= $max; $rowNumber++) {
            $columnIdx = Coordinate::columnIndexFromString(static::COLUMN_TUBE_ACCESSION_ID);

            $cell = $worksheet->getCellByColumnAndRow($columnIdx, $rowNumber);
            if (!$cell) {
                throw new \RuntimeException(sprintf('Cannot find Cell for Column %s Row %d', self::COLUMN_TUBE_ACCESSION_ID, $rowNumber));
            }

            $rawTubeId = trim($cell->getValue());
            if ($rawTubeId) {
                $tubeAccessionIds[] = $rawTubeId;
            }
        }

        return $tubeAccessionIds;
    }

    /**
     * Processes the import
     *
     * Results will be stored in the $output property
     *
     * Messages (including errors) will be stored in the $messages property
     */
    public function process($commit = false)
    {
        if ($this->output !== null) return $this->output;

        $output = [
            'updated' => [],
        ];

        $rowIndex = 2;
        $column = 'I';
        $rawWellPlateId = $this->worksheet->getCellValue($rowIndex, $column);
        if (!$rawWellPlateId) {
            $this->messages[] = ImportMessage::newError(
                'Well Plate ID cannot be located in uploaded file',
                $rowIndex,
                $column
            );
        }
        $wellPlate = $this->findOrCreateWellPlate($rawWellPlateId);

        // Created and updated can be figured out from file
        for ($rowNumber = $this->startingRow; $rowNumber <= $this->worksheet->getNumRows(); $rowNumber++) {
            $rawTubeId = $this->worksheet->getCellValue($rowNumber, $this->columnMap['tubeAccessionId']);
            $rawWellPosition = $this->worksheet->getCellValue($rowNumber, $this->columnMap['wellPosition']);

            // Validation methods return false if a field is invalid (and append to $this->messages)
            $rowOk = true;
            $rowOk = $this->validateTubeId($rawTubeId, $rowNumber) && $rowOk;
            $rowOk = $this->validateWellPosition($rawWellPosition, $rowNumber) && $rowOk;

            // If any field failed validation do not import the row
            if (!$rowOk) continue;

            // Tube / Specimen already validated
            $tube = $this->findTube($rawTubeId);
            $specimen = $tube->getSpecimen();

            $specimen->addWellPlate($wellPlate, $rawWellPosition);

            $resultAction = 'updated';

            // Store in output
            $output[$resultAction][] = [
                'tubeAccessionId' => $rawTubeId,
                'rnaWellPlateId' => $rawWellPlateId,
                'rnaWellPosition' => $rawWellPosition,
            ];
        }

        $this->output = $output;

        // Get rid of all entities so nothing is saved when not doing a commit
        if (!$commit) {
            $this->getEntityManager()->clear();
        }

        return $this->output;
    }

    /**
     * Returns true if $raw is valid
     *
     * Otherwise, adds an error message to $this->messages and returns false
     */
    private function validateWellPosition($rawWellPosition, $rowNumber): bool
    {
        if (!$rawWellPosition) {
            $this->messages[] = ImportMessage::newError(
                'Position cannot be blank',
                $rowNumber,
                $this->columnMap['wellPosition']
            );
            return false;
        }

        return true;
    }

    /**
     * Returns true if $raw is valid
     *
     * Otherwise, adds an error message to $this->messages and returns false
     */
    private function validateTubeId($rawTubeId, $rowNumber) : bool
    {
        if (!$rawTubeId) {
            $this->messages[] = ImportMessage::newError(
                'Tube ID cannot be blank',
                $rowNumber,
                $this->columnMap['tubeAccessionId']
            );
            return false;
        }

        // Ensure Tube can be found
        $tube = $this->findTube($rawTubeId);
        if (!$tube) {
            $this->messages[] = ImportMessage::newError(
                'Tube not found by Tube ID',
                $rowNumber,
                $this->columnMap['tubeAccessionId']
            );
            return false;
        }

        // Ensure Tube has a Specimen
        if (!$tube->getSpecimen()) {
            $this->messages[] = ImportMessage::newError(
                'Tube found but does not have a Specimen associated with it',
                $rowNumber,
                $this->columnMap['tubeAccessionId']
            );
            return false;
        }

        return true;
    }

    private function findTube($rawTubeAccessionId): ?Tube
    {
        // Cached?
        if (isset($this->seenTubes[$rawTubeAccessionId])) {
            return $this->seenTubes[$rawTubeAccessionId];
        }

        $tube = $this->tubeRepo->findOneWithSpecimenLoaded($rawTubeAccessionId);
        if (!$tube) {
            return null;
        }

        // Cache
        $this->seenTubes[$rawTubeAccessionId] = $tube;

        return $tube;
    }

    private function findOrCreateWellPlate(string $rawWellPlateId): WellPlate
    {
        $wellPlate = $this->em
            ->getRepository(WellPlate::class)
            ->findOneByBarcode($rawWellPlateId);

        if (!$wellPlate) {
            $wellPlate = new WellPlate($rawWellPlateId);
            $this->em->persist($wellPlate);
        }

        return $wellPlate;
    }
}

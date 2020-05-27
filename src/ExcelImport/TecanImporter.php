<?php

namespace App\ExcelImport;

use App\Entity\AppUser;
use App\Entity\ExcelImportCell;
use App\Entity\ExcelImportWorkbook;
use App\Entity\ExcelImportWorksheet;
use App\Entity\SpecimenResultQPCR;
use App\Entity\Tube;
use App\Repository\TubeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class TecanImporter extends BaseExcelImporter
{
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

    /**
     * @var string[] Keys are internal identifier, Values are Column-Row cells where that data is held
     */
    private $cellMap = [];

    public function __construct(EntityManagerInterface $em, ExcelImportWorksheet $worksheet)
    {
        $this->setEntityManager($em);
        $this->tubeRepo = $em->getRepository(Tube::class);

        parent::__construct($worksheet);

        $this->startingRow = 3; // Tecan output has 2 rows of header info

        $this->columnMap = [
            'wellPosition' => 'A', // 1-96 for 96-well position
            'tubeAccessionId' => 'F',
        ];

        $this->cellMap = [
            'rnaWellPlateId' => 'I2',
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

    public static function createWorkbookFromUpload(UploadedFile $file, AppUser $uploadedByUser) : ExcelImportWorkbook
    {
        $reader = new TecanOutputReader();

        $path = $file->getRealPath();
        $spreadsheet = $reader->load($path);

        $importWorkbook = new ExcelImportWorkbook();
        $importWorkbook->setFilename($file->getClientOriginalName());
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
            'created' => [],
            'updated' => [],
        ];

        // Created and updated can be figured out from file
        for ($rowNumber = $this->startingRow; $rowNumber <= $this->worksheet->getNumRows(); $rowNumber++) {
            $rawTubeId = $this->worksheet->getCellValue($rowNumber, $this->columnMap['tubeAccessionId']);

            $this->messages[] = ImportMessage::newMessage(
                sprintf('Row %d found Raw Tube ID "%s"'."\n", $rowNumber, $rawTubeId),
                $rowNumber,
                $this->columnMap['tubeAccessionId']
            );
            continue; // TODO: Remove and continue parsing when works

            // Case-insensitive so these map directly to entity constants

            // Validation methods return false if a field is invalid (and append to $this->messages)
            $rowOk = true;
            $rowOk = $this->validateTubeId($rawTubeId, $rowNumber) && $rowOk;

            // If any field failed validation do not import the row
            if (!$rowOk) continue;

            // Tube already validated
            $tube = $this->findTube($rawTubeId);
            $specimen = $tube->getSpecimen();

            var_dump(sprintf("Tube ID %s maps to Specimen ID %s", $tube->getAccessionId(), $specimen->getAccessionId()));

//            // "updated" if adding a new result when one already exists
//            // "created" if adding first result
//            $resultAction = count($specimen->getQPCRResults(1)) === 1 ? 'updated' : 'created';
            $resultAction = 'created';
//
//            // New Result
//            $qpcr = new SpecimenResultQPCR($specimen);
//            $qpcr->setConclusion($rawConclusion);

//            $this->getEntityManager()->persist($qpcr);
//
            // Store in output
            $output[$resultAction][] = $qpcr;
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
    private function validateConclusion($rawConclusion, $rowNumber): bool
    {
        if (!$rawConclusion) {
            $this->messages[] = ImportMessage::newError(
                'Conclusion cannot be blank',
                $rowNumber,
                $this->columnMap['conclusion']
            );
            return false;
        }

        // Conclusion must be valid
        if (!SpecimenResultQPCR::isValidConclusion($rawConclusion)) {
            $this->messages[] = ImportMessage::newError(
                'Conclusion value not supported',
                $rowNumber,
                $this->columnMap['conclusion']
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
}

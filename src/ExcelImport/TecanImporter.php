<?php

namespace App\ExcelImport;

use App\Entity\AppUser;
use App\Entity\ExcelImportCell;
use App\Entity\ExcelImportWorkbook;
use App\Entity\ExcelImportWorksheet;
use App\Entity\Specimen;
use App\Entity\SpecimenWell;
use App\Entity\Tube;
use App\Entity\WellPlate;
use App\Repository\TubeRepository;
use Doctrine\ORM\EntityManager;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class TecanImporter extends BaseExcelImporter
{
    const STARTING_ROW = 3;

    const WELL_POSITION_ROW = 1;
    const WELL_POSITION_COLUMN = 'A';
    const WELL_POSITION_HEADER = 'Position';

    const TUBE_ID_ROW = 1;
    const TUBE_ID_COLUMN = 'F';
    const TUBE_ID_HEADER = 'SRCTubeID';

    const BARCODE_ROW = 2;
    const BARCODE_COLUMN = 'I';

    /**
     * @var TubeRepository
     */
    private $tubeRepo;

    /**
     * @var Tube[]
     */
    private $processedTubes = [];

    /**
     * Cache of found Tubes used instead of query caching
     *
     * @var array Keys Tube.accessionId; Values Tube entity
     */
    private $seenTubes = [];

    public function __construct(EntityManager $em, ExcelImportWorksheet $worksheet, ?string $filename)
    {
        $this->setEntityManager($em);
        $this->tubeRepo = $em->getRepository(Tube::class);

        parent::__construct($worksheet, $filename);

        $this->startingRow = self::STARTING_ROW; // Tecan output has 2 rows of header info

        $this->columnMap = [
            'wellPosition' => self::WELL_POSITION_COLUMN, // 1-96 for 96-well position
            'tubeAccessionId' => self::TUBE_ID_COLUMN,
        ];
    }

    /**
     * Returns true if there is at least one group associated with $action
     *
     * $action can be:
     *  - created
     *  - updated
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

    /**
     * OVERRIDDEN to validate Workbook meets import expectations
     */
    public static function createExcelImportWorkbookFromUpload(UploadedFile $file, AppUser $uploadedByUser): ExcelImportWorkbook
    {
        $importWorkbook = parent::createExcelImportWorkbookFromUpload($file, $uploadedByUser);

        self::mustMeetFileFormatExpectations($importWorkbook->getFirstWorksheet());

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

        return static::getColumnValues($worksheet, static::TUBE_ID_COLUMN, static::STARTING_ROW);
    }

    /**
     * Processes the import
     *
     * Results will be stored in the $output property
     *
     * Messages (including errors) will be stored in the $messages property
     *
     * @return Tube[]
     */
    public function process($commit = false)
    {
        if ($this->output !== null) {
            return $this->processedTubes;
        }

        $output = [
            'created' => [],
            'updated' => [],
        ];

        // Well Plate Barcode
        $rowIndex = self::BARCODE_ROW;
        $column = self::BARCODE_COLUMN;
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
            // If all values are blank assume it's just empty excel data
            if ($this->rowDataBlank($rowNumber)) continue;

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

            // Whether created or updated
            $resultAction = $specimen->isOnWellPlate($wellPlate) ? 'updated' : 'created';

            // Get SpecimenWell where this Specimen is stored on the Well Plate
            $well = $this->findOrCreateWell($wellPlate, $specimen);

            // Set position on this Well Plate
            // Positions from Excel begin at 1
            try {
                $alphanumericPosition = SpecimenWell::positionAlphanumericFromInt($rawWellPosition);
                $well->setPositionAlphanumeric($alphanumericPosition);
            } catch (\Exception $e) {
                $this->messages[] = ImportMessage::newError(
                    $e->getMessage(),
                    $rowNumber,
                    $this->columnMap['wellPosition']
                );
            }

            $this->em->persist($well);

            // Store in output
            $output[$resultAction][] = [
                'tubeAccessionId' => $rawTubeId,
                'rnaWellPlateId' => $rawWellPlateId,
                'rnaWellPosition' => sprintf("%s (%d)", $well->getPositionAlphanumeric(), $rawWellPosition),
            ];

            $this->processedTubes[] = $tube;
        }

        $this->output = $output;

        // Get rid of all entities so nothing is saved when not doing a commit
        if (!$commit) {
            $this->getEntityManager()->clear();
        }

        return $this->processedTubes;
    }

    /**
     * Tecan upload provides Position in integer format as 1 thru 96.
     * Verify this position is valid.
     *
     * Returns true if $rawWellPosition is valid.
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

        $invalidPositionMsg = sprintf('Position must be between %d and %d', SpecimenWell::minIntegerPosition, SpecimenWell::maxIntegerPosition);
        if (
            $rawWellPosition < SpecimenWell::minIntegerPosition
            || $rawWellPosition > SpecimenWell::maxIntegerPosition
        ) {
            $this->messages[] = ImportMessage::newError(
                $invalidPositionMsg,
                $rowNumber,
                $this->columnMap['wellPosition']
            );
        }

        try {
            SpecimenWell::positionAlphanumericFromInt($rawWellPosition);
        } catch (\Exception $e) {
            $this->messages[] = ImportMessage::newError(
                $invalidPositionMsg,
                $rowNumber,
                $this->columnMap['wellPosition']
            );
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

        // Ensure Tube in correct status
        if (!$tube->willAllowTecanImport()) {
            $this->messages[] = ImportMessage::newError(
                'Tube not in correct status to allow importing',
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

        $tube = $this->tubeRepo->findOneByAccessionId($rawTubeAccessionId);
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

    /**
     * Throws Exception if uploaded file does not have data in assumed location.
     */
    private static function mustMeetFileFormatExpectations(ExcelImportWorksheet $worksheet)
    {
        // Position
        $row = self::WELL_POSITION_ROW;
        $column = self::WELL_POSITION_COLUMN;
        $found = $worksheet->getCellValue($row, $column);
        if (self::WELL_POSITION_HEADER !== $found) {
            throw new \RuntimeException(sprintf('Cannot find column %s. Expected to find at cell %s%d', self::WELL_POSITION_HEADER, $column, $row));
        }

        // Tube Accession ID
        $row = self::TUBE_ID_ROW;
        $column = self::TUBE_ID_COLUMN;
        $found = $worksheet->getCellValue($row, $column);
        if (self::TUBE_ID_HEADER !== $found) {
            throw new \RuntimeException(sprintf('Cannot find column %s. Expected to find at cell %s%d', self::TUBE_ID_HEADER, $column, $row));
        }
    }

    private function findOrCreateWell(WellPlate $wellPlate, Specimen $specimen): SpecimenWell
    {
        $well = null;

        if ($specimen->isOnWellPlate($wellPlate)) {
            // Specimen is already on this plate
            // Find if there's a well without a position
            foreach($specimen->getWellsOnPlate($wellPlate) as $existingWell) {
                if (!$existingWell->getPositionAlphanumeric()) {
                    $well = $existingWell;
                    break;
                }
            }
        }

        if (!$well) {
            $well = new SpecimenWell($wellPlate, $specimen);
        }

        return $well;
    }
}

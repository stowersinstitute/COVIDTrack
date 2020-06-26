<?php

namespace App\ExcelImport;

use App\Entity\ExcelImportWorksheet;
use App\Entity\Tube;
use App\Entity\WellPlate;
use App\Repository\TubeRepository;
use Doctrine\ORM\EntityManager;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Check-in Blood Specimens using Excel import
 */
class TubeCheckinBloodImporter extends BaseExcelImporter
{
    /**
     * Excel cell value when Tube is Accepted. Case insensitive.
     */
    const STATUS_ACCEPTED = 'ACCEPTED';
    /**
     * Excel cell value when Tube is Rejected. Case insensitive.
     */
    const STATUS_REJECTED = 'REJECTED';

    /**
     * Local cache for record lookup
     * @var array Keys Tube.accessionId; Values Tube
     */
    private $tubeCache = [];

    /**
     * Local cache for WellPlate lookup
     * @var array Keys WellPlate.barcode; Values WellPlate
     */
    private $platesCache = [];

    public function __construct(EntityManager $em, ExcelImportWorksheet $worksheet)
    {
        $this->setEntityManager($em);

        parent::__construct($worksheet);

        // Array keys are available in HTML views to look up imported data
        $this->columnMap = [
            'tubeAccessionId' => 'A',
            'acceptedStatus' => 'B',
            'wellPlateBarcode' => 'C',
            'wellIdentifier' => 'D',
            'wellPosition' => 'E',
            'kitType' => 'F',
            'username' => 'G',
        ];
    }

    /**
     * Find and replace Tube Accession IDs with Specimen Accession IDs
     * in the given file.
     *
     * @return Spreadsheet Returns the entire Excel workbook with Tube IDs replaced
     */
    public static function convertTubesToSpecimens(string $excelFilePath, TubeRepository $tubeRepo): Spreadsheet
    {
        // Read file in as Excel workbook, get first worksheet
        $workbook = IOFactory::load($excelFilePath);
        $worksheet = $workbook->getActiveSheet();

        // Parse Tube Accession IDs from spreadsheet
        $columnLetter = 'A';
        $rawTubeAccessionIds = self::getColumnValues($worksheet, $columnLetter);
        foreach ($rawTubeAccessionIds as $rowNumber => $rawTubeAccessionId) {
            $tube = $tubeRepo->findOneByAccessionId($rawTubeAccessionId);

            if (!$tube || !$tube->getSpecimen()) {
                throw new \InvalidArgumentException(sprintf('Cannot find Tube for Tube Accession ID "%s"', $rawTubeAccessionId ));
            }

            $specimenAccessionId = $tube->getSpecimen()->getAccessionId();
            $coordinate = $columnLetter . $rowNumber;
            $cell = $worksheet->getCell($coordinate);
            if ($cell) {
                $cell->setValue($specimenAccessionId);
            }
        }

        return $workbook;
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
     * Applies the data in the excel file to existing entities
     *
     * Modified entities are returned
     */
    public function process($commit = false)
    {
        if ($this->output !== null) return $this->output;

        // Array values are the raw values from each row
        // See $this->columnMap for available keys
        $output = [
            'accepted' => [],
            'rejected' => [],
        ];

        // Track Tubes imported during this import.
        // Used for duplicate import checking.
        $importedTubes = [];

        for ($rowNumber=$this->startingRow; $rowNumber <= $this->worksheet->getNumRows(); $rowNumber++) {
            // If all values are blank assume it's just empty excel data
            if ($this->rowDataBlank($rowNumber)) continue;

            // Validation methods return false if a field is invalid (and append to $this->messages)
            $rowOk = true;

            $rawValues = $this->buildColumnMapValues($rowNumber);

            $rawTubeId = $rawValues['tubeAccessionId'];
            $rowOk = $this->validateTube($rawTubeId, $rowNumber, $importedTubes) && $rowOk;

            $rawAcceptedStatus = $rawValues['acceptedStatus'];
            $rawAcceptedStatus = strtoupper($rawAcceptedStatus);
            $rowOk = $this->validateAcceptOrReject($rawAcceptedStatus, $rowNumber) && $rowOk;

            $wellPlateBarcode = $rawValues['wellPlateBarcode'];
            $rowOk = $this->validateWellPlateBarcode($wellPlateBarcode, $rowNumber) && $rowOk;

            $wellIdentifier = $rawValues['wellIdentifier'];
            $rowOk = $this->validateWellIdentifier($wellIdentifier, $rowNumber) && $rowOk;

            $wellPosition = $rawValues['wellPosition'];
            $rowOk = $this->validateWellPosition($wellPosition, $rowNumber) && $rowOk;

            $rawKitType = $rawValues['kitType'];
            $rowOk = $this->validateKitType($rawKitType, $rowNumber) && $rowOk;

            $rawUsername = $rawValues['username'];
            $rowOk = $this->validateUsername($rawUsername, $rowNumber) && $rowOk;

            // If any field failed validation do not import the row
            if (!$rowOk) continue;

            // Tube already validated
            $tube = $this->findTube($rawTubeId);

            // Set accepted/rejected status
            switch ($rawAcceptedStatus) {
                case self::STATUS_ACCEPTED:
                    $tube->markAccepted($rawUsername);
                    $output['accepted'][$rowNumber] = $rawValues;
                    break;
                case self::STATUS_REJECTED:
                    $tube->markRejected($rawUsername);
                    $output['rejected'][$rowNumber] = $rawValues;
                    break;
            }

            // Create Well Plate if given
            if (strlen($wellPlateBarcode) > 0) {
                $plate = $this->findWellPlateOrMakeNew($wellPlateBarcode);
                $tube->addToWellPlate($plate);
            }

            // Kit Type
            $tube->setKitType($rawKitType);

            $importedTubes[$tube->getAccessionId()] = $tube;
        }

        if (!$commit) {
            $this->getEntityManager()->clear();
        }

        $this->output = $output;

        return $this->output;
    }

    /**
     * Returns true if $raw is valid
     *
     * Otherwise, adds an error message to $this->messages and returns false
     *
     * @param Tube[]  $seenTubes Tubes already imported during this import
     * @return bool
     */
    private function validateTube(string $rawTubeId, $rowNumber, array $seenTubes): bool
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

        // Tube Type must either be blank or previously marked as "Blood"
        if ($tube->getTubeType() && $tube->getTubeType() !== Tube::TYPE_BLOOD) {
            $this->messages[] = ImportMessage::newError(
                'Tube ID not marked to store Blood',
                $rowNumber,
                $this->columnMap['tubeAccessionId']
            );
            return false;
        }

        // Don't re-process same tube again
        if (isset($seenTubes[$tube->getAccessionId()])) {
            $this->messages[] = ImportMessage::newError(
                'Tube ID occurs more than once in uploaded workbook',
                $rowNumber,
                $this->columnMap['tubeAccessionId']
            );
            return false;
        }

        // Tubes must be in correct status to be checked-in
        if (!$tube->willAllowCheckinDecision()) {
            $this->messages[] = ImportMessage::newError(
                sprintf('Tube cannot be checked-in because it is in the wrong status: %s', $tube->getStatusText()),
                $rowNumber,
                $this->columnMap['tubeAccessionId']
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
    private function validateAcceptOrReject($raw, $rowNumber): bool
    {
        $validStatuses = [self::STATUS_ACCEPTED, self::STATUS_REJECTED];
        if (!in_array($raw, $validStatuses)) {
            $this->messages[] = ImportMessage::newError(
                sprintf('Accept/Reject must be one of: %s', join(", ", $validStatuses)),
                $rowNumber,
                $this->columnMap['acceptedStatus']
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
    private function validateWellPlateBarcode($rawWellPlateBarcode, $rowNumber): bool
    {
        // Well Plate Barcode cannot be blank
        if (!$rawWellPlateBarcode) {
            $this->messages[] = ImportMessage::newError(
                sprintf('Well Plate Barcode cannot be blank'),
                $rowNumber,
                $this->columnMap['wellPlateBarcode']
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
    private function validateWellIdentifier($rawWellIdentifier, $rowNumber): bool
    {
        // Cannot be blank
        if (!$rawWellIdentifier) {
            $this->messages[] = ImportMessage::newError(
                sprintf('Well Identifier cannot be blank'),
                $rowNumber,
                $this->columnMap['wellIdentifier']
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
    private function validateWellPosition($rawWellPosition, $rowNumber): bool
    {
        // Well Position cannot be blank
        if (!$rawWellPosition) {
            $this->messages[] = ImportMessage::newError(
                sprintf('Well Position cannot be blank'),
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
    private function validateKitType($rawKitType, $rowNumber): bool
    {
        // No validation rules
        return true;
    }

    /**
     * Returns true if $raw is valid
     *
     * Otherwise, adds an error message to $this->messages and returns false
     */
    private function validateUsername(string $rawUsername, $rowNumber): bool
    {
        if (!$rawUsername) {
            $this->messages[] = ImportMessage::newError(
                sprintf('Technician Username cannot be blank'),
                $rowNumber,
                $this->columnMap['username']
            );
            return false;
        }

        $maxLen = 255; // From Tube::checkedInByUsername
        if (strlen($rawUsername) > $maxLen) {
            $this->messages[] = ImportMessage::newError(
                sprintf('Technician Username must be less than %s characters', $maxLen),
                $rowNumber,
                $this->columnMap['username']
            );
            return false;
        }

        return true;
    }

    private function findTube(string $accessionId) : ?Tube
    {
        if (isset($this->tubeCache[$accessionId])) {
            return $this->tubeCache[$accessionId];
        }

        /** @var Tube $tube */
        $tube = $this->em
            ->getRepository(Tube::class)
            ->findOneByAccessionId($accessionId);
        if (!$tube) {
            return null;
        }

        $this->tubeCache[$accessionId] = $tube;

        return $tube;
    }

    private function findWellPlateOrMakeNew(string $rawWellPlateId): WellPlate
    {
        if (isset($this->platesCache[$rawWellPlateId])) {
            return $this->platesCache[$rawWellPlateId];
        }

        $plate = $this->em
            ->getRepository(WellPlate::class)
            ->findOneByBarcode($rawWellPlateId);
        if (!$plate) {
            $plate = new WellPlate($rawWellPlateId);
            $this->em->persist($plate);

            $this->platesCache[$rawWellPlateId] = $plate;
        }

        return $plate;
    }

    /**
     * Get cell values for each cell in given row.
     * Returned array has same keys as $this->columnMap.
     */
    private function buildColumnMapValues(int $rowNumber): array
    {
        $map = [];
        foreach ($this->columnMap as $columnId => $letter) {
            $map[$columnId] = $this->worksheet->getCellValue($rowNumber, $letter);
        }

        return $map;
    }
}

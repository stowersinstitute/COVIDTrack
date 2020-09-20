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
     * Tubes imported by this importer.
     * @var Tube[]
     */
    private $importedTubes = [];

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

    public function __construct(EntityManager $em, ExcelImportWorksheet $worksheet, ?string $filename)
    {
        $this->setEntityManager($em);

        parent::__construct($worksheet, $filename);

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
            // Skip converting rows without a Tube ID
            if ((string)$rawTubeAccessionId === '') continue;

            $tube = $tubeRepo->findOneByAccessionId($rawTubeAccessionId);

            if (!$tube) {
                throw new \InvalidArgumentException(sprintf('Cannot find Tube for Tube Accession ID "%s"', $rawTubeAccessionId ));
            }

            // Must be in correct workflow state
            if (!$tube->willAllowCheckinDecision()) {
                throw new \InvalidArgumentException(sprintf('Tube Accession ID "%s" has not been dropped off at a kiosk', $rawTubeAccessionId ));
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
     *
     * @return Tube[]
     */
    public function process($commit = false)
    {
        if ($this->output !== null) {
            return $this->importedTubes;
        }

        // Array values are the raw values from each row
        // See $this->columnMap for available keys
        $output = [
            'accepted' => [],
            'rejected' => [],
        ];

        for ($rowNumber=$this->startingRow; $rowNumber <= $this->worksheet->getNumRows(); $rowNumber++) {
            // If all values are blank assume it's just empty excel data
            if ($this->rowDataBlank($rowNumber)) continue;

            // Validation methods return false if a field is invalid (and append to $this->messages)
            $rowOk = true;

            $rawValues = $this->buildColumnMapValues($rowNumber);

            $rawTubeId = $rawValues['tubeAccessionId'];
            $rowOk = $this->validateTube($rawTubeId, $rowNumber) && $rowOk;

            $rawAcceptedStatus = $rawValues['acceptedStatus'];
            $rawAcceptedStatus = strtoupper($rawAcceptedStatus);
            $rowOk = $this->validateAcceptOrReject($rawAcceptedStatus, $rowNumber) && $rowOk;

            $wellPlateBarcode = $rawValues['wellPlateBarcode'];
            $rowOk = $this->validateWellPlateBarcode($wellPlateBarcode, $rawAcceptedStatus, $rowNumber) && $rowOk;

            $wellIdentifier = $rawValues['wellIdentifier'];
            $rowOk = $this->validateWellIdentifier($wellIdentifier, $rawAcceptedStatus, $rowNumber) && $rowOk;

            $wellPosition = $rawValues['wellPosition'];
            $rowOk = $this->validateWellPosition($wellPosition, $rawAcceptedStatus, $rowNumber) && $rowOk;

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

            // Accepted Tubes will track storage details
            if ($rawAcceptedStatus === self::STATUS_ACCEPTED) {
                // Well Plate is either created or fetched,
                // and this Tube/Specimen added at given Well Position
                $plate = $this->findWellPlateOrMakeNew($wellPlateBarcode);
                $well = $tube->addToWellPlate($plate, $wellPosition);

                // Well Identifier
                $well->setWellIdentifier($wellIdentifier);
            }

            // Kit Type
            $tube->setKitType($rawKitType);

            $this->importedTubes[$tube->getAccessionId()] = $tube;
        }

        if (!$commit) {
            $this->getEntityManager()->clear();
        }

        $this->output = $output;

        $this->importedTubes = array_values($this->importedTubes);

        return $this->importedTubes;
    }

    /**
     * Returns true if $raw is valid
     *
     * Otherwise, adds an error message to $this->messages and returns false
     *
     * @return bool
     */
    private function validateTube(string $rawTubeId, $rowNumber): bool
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
                sprintf('Tube not found by Tube ID "%s"', $rawTubeId),
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
        if (isset($this->importedTubes[$tube->getAccessionId()])) {
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
    private function validateWellPlateBarcode($rawWellPlateBarcode, string $acceptedStatus, $rowNumber): bool
    {
        // Well Plate Barcode can only be blank for Rejected tubes, because
        // rejected tubes will never be stored on a Well Plate
        if ($acceptedStatus !== self::STATUS_REJECTED && !$rawWellPlateBarcode) {
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
    private function validateWellIdentifier($rawWellIdentifier, string $acceptedStatus, $rowNumber): bool
    {
        // Can only be blank for Rejected tubes, because
        // rejected tubes will never be stored on a Well Plate
        if ($acceptedStatus !== self::STATUS_REJECTED && !$rawWellIdentifier) {
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
    private function validateWellPosition($rawWellPosition, string $acceptedStatus, $rowNumber): bool
    {
        // Can only be blank for Rejected tubes, because
        // rejected tubes will never be stored on a Well Plate
        if ($acceptedStatus !== self::STATUS_REJECTED && !$rawWellPosition) {
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
    private function validateUsername($rawUsername, $rowNumber): bool
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

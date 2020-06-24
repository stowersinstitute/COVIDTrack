<?php

namespace App\ExcelImport;

use App\Entity\ExcelImportWorksheet;
use App\Entity\Tube;
use App\Entity\WellPlate;
use Doctrine\ORM\EntityManager;

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

        $this->columnMap = [
            'tubeId' => 'A',
            'acceptedStatus' => 'B',
            'rnaWellPlateBarcode' => 'C',
            'kitType' => 'D',
            'username' => 'E',
        ];
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

            $rawTubeId = $this->worksheet->getCellValue($rowNumber, $this->columnMap['tubeId']);
            $rawAcceptedStatus = $this->worksheet->getCellValue($rowNumber, $this->columnMap['acceptedStatus']);
            $rawAcceptedStatus = strtoupper($rawAcceptedStatus);
            $rawWellPlateBarcode = $this->worksheet->getCellValue($rowNumber, $this->columnMap['rnaWellPlateBarcode']);
            $rawKitType = $this->worksheet->getCellValue($rowNumber, $this->columnMap['kitType']);
            $rawUsername = $this->worksheet->getCellValue($rowNumber, $this->columnMap['username']);

            // Validation methods return false if a field is invalid (and append to $this->messages)
            $rowOk = true;
            $rowOk = $this->validateTube($rawTubeId, $rowNumber, $importedTubes) && $rowOk;
            $rowOk = $this->validateAcceptOrReject($rawAcceptedStatus, $rowNumber) && $rowOk;
            $rowOk = $this->validateWellPlateBarcode($rawWellPlateBarcode, $rowNumber) && $rowOk;
            $rowOk = $this->validateKitType($rawKitType, $rowNumber) && $rowOk;
            $rowOk = $this->validateUsername($rawUsername, $rowNumber) && $rowOk;

            // If any field failed validation do not import the row
            if (!$rowOk) continue;

            // Tube already validated
            $tube = $this->findTube($rawTubeId);

            // Set accepted/rejected status
            switch ($rawAcceptedStatus) {
                case self::STATUS_ACCEPTED:
                    $tube->markAccepted($rawUsername);
                    $output['accepted'][] = $tube;
                    break;
                case self::STATUS_REJECTED:
                    $tube->markRejected($rawUsername);
                    $output['rejected'][] = $tube;
                    break;
            }

            // Create Well Plate if given
            if (strlen($rawWellPlateBarcode) > 0) {
                $plate = $this->findWellPlateOrMakeNew($rawWellPlateBarcode);
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
                $this->columnMap['tubeId']
            );
            return false;
        }

        // Ensure Tube can be found
        $tube = $this->findTube($rawTubeId);
        if (!$tube) {
            $this->messages[] = ImportMessage::newError(
                'Tube not found by Tube ID',
                $rowNumber,
                $this->columnMap['tubeId']
            );
            return false;
        }

        // Don't re-process same tube again
        if (isset($seenTubes[$tube->getAccessionId()])) {
            $this->messages[] = ImportMessage::newError(
                'Tube ID occurs more than once in uploaded workbook',
                $rowNumber,
                $this->columnMap['tubeId']
            );
            return false;
        }

        // Tubes must be in correct status to be checked-in
        if (!$tube->willAllowCheckinDecision()) {
            $this->messages[] = ImportMessage::newError(
                sprintf('Tube cannot be checked-in because it is in the wrong status: %s', $tube->getStatusText()),
                $rowNumber,
                $this->columnMap['tubeId']
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
        // No validation rules
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
}

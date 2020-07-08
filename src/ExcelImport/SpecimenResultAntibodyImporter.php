<?php

namespace App\ExcelImport;

use App\Entity\ExcelImportWorksheet;
use App\Entity\Specimen;
use App\Entity\SpecimenResultAntibody;
use App\Entity\WellPlate;
use Doctrine\ORM\EntityManager;

/**
 * Import SpecimenResultAntibody records via Excel
 */
class SpecimenResultAntibodyImporter extends BaseExcelImporter
{
    /**
     * @var \App\Entity\SpecimenRepository
     */
    private $specimenRepo;

    /**
     * @var \App\Repository\WellPlateRepository
     */
    private $plateRepo;

    /**
     * Cache of found Specimen used instead of query caching
     *
     * @var array Keys Specimen.id; Values Specimen entity
     */
    private $seenSpecimens = [];

    /**
     * Cache of found WellPlate used instead of query caching
     *
     * @var array Keys WellPlate.barcode; Values WellPlate entity
     */
    private $seenPlates = [];

    /**
     * Each result entity processed during this import.
     *
     * @var SpecimenResultAntibody[]
     */
    private $processedResults = [];

    public function __construct(EntityManager $em, ExcelImportWorksheet $worksheet)
    {
        $this->setEntityManager($em);
        $this->specimenRepo = $em->getRepository(Specimen::class);
        $this->plateRepo = $em->getRepository(WellPlate::class);

        parent::__construct($worksheet);

        $this->columnMap = [
            'specimenId' => 'A',
            'wellIdentifier' => 'B',
            'conclusion' => 'C',
            'signal' => 'D',
            'wellPosition' => 'E',
            'plateBarcode' => 'G',
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
     * Processes the import
     *
     * Results will be stored in the $output property
     *
     * Messages (including errors) will be stored in the $messages property
     *
     * @return SpecimenResultAntibody[] Results processed during this import
     */
    public function process($commit = false)
    {
        if ($this->output !== null) {
            return $this->processedResults;
        }

        $output = [
            'created' => [],
            'updated' => [],
        ];

        // Created and updated can be figured out from the Excel file
        for ($rowNumber = $this->startingRow; $rowNumber <= $this->worksheet->getNumRows(); $rowNumber++) {
            // If all values are blank assume it's just empty excel data
            if ($this->rowDataBlank($rowNumber)) continue;

            // Validation methods return false if a field is invalid (and append to $this->messages)
            $rowOk = true;

            $rawSpecimenId = $this->worksheet->getCellValue($rowNumber, $this->columnMap['specimenId']);
            $rowOk = $this->validateSpecimenId($rawSpecimenId, $rowNumber) && $rowOk;

            $rawWellIdentifier = $this->worksheet->getCellValue($rowNumber, $this->columnMap['wellIdentifier']);
            $rowOk = $this->validateWellIdentifier($rawWellIdentifier, $rowNumber) && $rowOk;

            // Case-insensitive so these map directly to entity constants
            $rawConclusion = strtoupper($this->worksheet->getCellValue($rowNumber, $this->columnMap['conclusion']));
            $rowOk = $this->validateConclusion($rawConclusion, $rowNumber) && $rowOk;

            $rawSignal = $this->worksheet->getCellValue($rowNumber, $this->columnMap['signal']);
            $rowOk = $this->validateSignal($rawSignal, $rowNumber) && $rowOk;

            $rawWellPosition = $this->worksheet->getCellValue($rowNumber, $this->columnMap['wellPosition']);
            $rawPlateBarcode = $this->worksheet->getCellValue($rowNumber, $this->columnMap['plateBarcode']);
            $rowOk = $this->validatePlateAndPosition($rawPlateBarcode, $rawWellPosition, $rawWellIdentifier, $rawSpecimenId, $rowNumber) && $rowOk;

            // If any field failed validation do not import the row
            if (!$rowOk) continue;

            // Specimen already validated
            $specimen = $this->findSpecimen($rawSpecimenId);
            $plate = $this->findPlate($rawPlateBarcode);
            $well = $specimen->getWellAtPosition($plate, $rawWellPosition);

            // "updated" if adding a new result when one already exists
            // "created" if adding first result
            $resultAction = count($specimen->getAntibodyResults(1)) === 1 ? 'updated' : 'created';

            // New Result
            $result = new SpecimenResultAntibody($well, $rawConclusion, $rawSignal);

            $this->getEntityManager()->persist($result);

            // Store in output
            $output[$resultAction][] = $result;

            $this->processedResults[] = $result;
        }

        $this->output = $output;

        // Get rid of all entities so nothing is saved when not doing a commit
        if (!$commit) {
            $this->getEntityManager()->clear();
        }

        return $this->processedResults;
    }

    /**
     * Returns true if $raw is valid
     *
     * Otherwise, adds an error message to $this->messages and returns false
     */
    private function validateSpecimenId($rawSpecimenId, $rowNumber) : bool
    {
        if (!$rawSpecimenId) {
            $this->messages[] = ImportMessage::newError(
                'Specimen ID cannot be blank',
                $rowNumber,
                $this->columnMap['specimenId']
            );
            return false;
        }

        // Ensure Specimen can be found
        $specimen = $this->findSpecimen($rawSpecimenId);
        if (!$specimen) {
            $this->messages[] = ImportMessage::newError(
                sprintf('Cannot find Specimen by Specimen ID "%s"', $rawSpecimenId),
                $rowNumber,
                $this->columnMap['specimenId']
            );
            return false;
        }

        // Ensure in correct workflow status
        if (!$specimen->willAllowAddingResults()) {
            $this->messages[] = ImportMessage::newError(
                'Specimen not in correct status to allow importing results',
                $rowNumber,
                $this->columnMap['specimenId']
            );
            return false;
        }

        return true;
    }

    private function findSpecimen($rawSpecimenId): ?Specimen
    {
        // Cached?
        if (isset($this->seenSpecimens[$rawSpecimenId])) {
            return $this->seenSpecimens[$rawSpecimenId];
        }

        $specimen = $this->specimenRepo->findOneByAccessionId($rawSpecimenId);
        if (!$specimen) {
            return null;
        }

        // Cache
        $this->seenSpecimens[$rawSpecimenId] = $specimen;

        return $specimen;
    }

    private function findPlate($rawPlateBarcode): ?WellPlate
    {
        // Cached?
        if (isset($this->seenPlates[$rawPlateBarcode])) {
            return $this->seenPlates[$rawPlateBarcode];
        }

        $plate = $this->plateRepo->findOneByBarcode($rawPlateBarcode);
        if (!$plate) {
            return null;
        }

        // Cache
        $this->seenPlates[$rawPlateBarcode] = $plate;

        return $plate;
    }

    /**
     * Returns true if $raw is valid
     *
     * Otherwise, adds an error message to $this->messages and returns false
     */
    private function validateWellIdentifier($rawWellIdentifier, $rowNumber) : bool
    {
        // No validation
        return true;
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
        if (!SpecimenResultAntibody::isValidConclusion($rawConclusion)) {
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
    private function validateSignal($rawSignal, $rowNumber): bool
    {
        if ($rawSignal === null) {
            $this->messages[] = ImportMessage::newError(
                'Signal cannot be blank',
                $rowNumber,
                $this->columnMap['signal']
            );
            return false;
        }

        // Check validity
        if (!SpecimenResultAntibody::isValidSignal($rawSignal)) {
            $this->messages[] = ImportMessage::newError(
                'Signal value not supported',
                $rowNumber,
                $this->columnMap['signal']
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
    private function validatePlateAndPosition(?string $rawPlateBarcode, ?string $rawPosition, ?string $rawWellIdentifier, string $rawSpecimenId, int $rowNumber): bool
    {
        // Plate Barcode required
        if ($rawPlateBarcode === null) {
            $this->messages[] = ImportMessage::newError(
                'Well Plate Barcode cannot be blank',
                $rowNumber,
                $this->columnMap['plateBarcode']
            );
            return false;
        }

        // Well Position required
        if ($rawPosition === null) {
            $this->messages[] = ImportMessage::newError(
                'Well Position cannot be blank',
                $rowNumber,
                $this->columnMap['wellPosition']
            );
            return false;
        }

        // Well Identifier required
        if ($rawPosition === null) {
            $this->messages[] = ImportMessage::newError(
                'Well Identifier cannot be blank',
                $rowNumber,
                $this->columnMap['wellIdentifier']
            );
            return false;
        }

        // Must find Well Plate by Barcode
        $wellPlate = $this->findPlate($rawPlateBarcode);
        if (!$wellPlate) {
            $this->messages[] = ImportMessage::newError(
                sprintf('Cannot find Well Plate by barcode "%s"', $rawPlateBarcode),
                $rowNumber,
                $this->columnMap['plateBarcode']
            );
            return false;
        }

        // Specimen must already be in a Well on this Well Plate
        $specimen = $this->findSpecimen($rawSpecimenId);
        if (!$specimen) {
            // Error message already added via validateSpecimenId
            return false;
        }
        if (!$specimen->isOnWellPlate($wellPlate)) {
            $this->messages[] = ImportMessage::newError(
                sprintf('Specimen "%s" not currently on Well Plate "%s"', $rawSpecimenId, $rawPlateBarcode),
                $rowNumber,
                $this->columnMap['plateBarcode']
            );
            return false;
        }

        // Get the specific Well at reported Position
        $well = $specimen->getWellAtPosition($wellPlate, $rawPosition);
        if (!$well) {
            // Specimen not at this position on plate in Results file.

            // Build list of positions to display in error message
            $wellPositions = [];
            foreach ($specimen->getWellsOnPlate($wellPlate) as $well) {
                if ($well->getPositionAlphanumeric()) {
                    $wellPositions[] = $well->getPositionAlphanumeric();
                }
            }
            $prnCurrentPositions = implode(', ', $wellPositions);
            if (count($wellPositions) === 0) {
                $prnCurrentPositions = 'but does not have any positions saved';
            }

            $this->messages[] = ImportMessage::newError(
                sprintf('Specimen "%s" currently in Well %s. Results file lists Well "%s". These must match.', $rawSpecimenId, $prnCurrentPositions, $rawPosition),
                $rowNumber,
                $this->columnMap['wellPosition']
            );
            return false;
        }

        // Well Identifier must match
        $currentWellIdentifier = $well->getWellIdentifier();
        if ($currentWellIdentifier !== $rawWellIdentifier) {
            $this->messages[] = ImportMessage::newError(
                sprintf('Well %s on Plate %s currently has Well ID "%s". Uploaded value is "%s". These must match.', $rawPosition, $rawPlateBarcode, $currentWellIdentifier, $rawWellIdentifier),
                $rowNumber,
                $this->columnMap['wellPosition']
            );
            return false;
        }

        return true;
    }
}

<?php

namespace App\ExcelImport;

use App\Entity\ExcelImportWorksheet;
use App\Entity\Specimen;
use App\Entity\SpecimenResultQPCR;
use Doctrine\ORM\EntityManagerInterface;

class SpecimenResultQPCRImporter extends BaseExcelImporter
{
    /**
     * @var \App\Entity\SpecimenRepository
     */
    private $specimenRepo;

    /**
     * Cache of found Specimen used instead of query caching
     *
     * @var array Keys Specimen.id; Values Specimen entity
     */
    private $seenSpecimens = [];

    public function __construct(EntityManagerInterface $em, ExcelImportWorksheet $worksheet)
    {
        $this->setEntityManager($em);
        $this->specimenRepo = $em->getRepository(Specimen::class);

        parent::__construct($worksheet);

        $this->columnMap = [
            'specimenId' => 'A',
            'conclusion' => 'B',
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
     */
    public function process($commit = false)
    {
        if ($this->output !== null) return $this->output;

        $output = [
            'created' => [],
            'updated' => [],
        ];

        // Created and updated can be figured out from the Excel file
        for ($rowNumber = $this->startingRow; $rowNumber <= $this->worksheet->getNumRows(); $rowNumber++) {
            $rawSpecimenId = $this->worksheet->getCellValue($rowNumber, $this->columnMap['specimenId']);
            $rawConclusion = strtoupper($this->worksheet->getCellValue($rowNumber, $this->columnMap['conclusion']));

            // Validation methods return false if a field is invalid (and append to $this->messages)
            $rowOk = true;
            $rowOk = $this->validateSpecimenId($rawSpecimenId, $rowNumber) && $rowOk;
            $rowOk = $this->validateConclusion($rawConclusion, $rowNumber) && $rowOk;

            // If any field failed validation do not import the row
            if (!$rowOk) continue;

            // Specimen already validated
            $specimen = $this->findSpecimen($rawSpecimenId);

            // "updated" if adding a new result when one already exists
            // "created" if adding first result
            $resultAction = count($specimen->getQPCRResults(1)) === 1 ? 'updated' : 'created';

            // New Result
            $qpcr = new SpecimenResultQPCR($specimen);
            $qpcr->setConclusion($rawConclusion);

            $this->getEntityManager()->persist($qpcr);

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
        if (!$this->findSpecimen($rawSpecimenId)) {
            $this->messages[] = ImportMessage::newError(
                'Specimen not found by Specimen ID',
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
}
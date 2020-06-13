<?php

namespace App\ExcelImport;

use App\Entity\ExcelImportWorksheet;
use App\Entity\Tube;

/**
 * Import list of Tube Accession IDs found on vendor Tubes received with
 * labels already on the Tube. Makes COVIDTrack aware of Tube Accession IDs
 * created outside the system.
 */
class TubeImporter extends BaseExcelImporter
{
    public function __construct(ExcelImportWorksheet $worksheet)
    {
        parent::__construct($worksheet);

        $this->columnMap = [
            'accessionId' => 'A',
        ];
    }

    /**
     * Processes the import
     *
     * Results will be stored in the $output property
     *
     * Messages (including errors) will be stored in the $messages property
     *
     * @return Tube[] Imported Tubes. Do not yet have Tube.id because EntityManager not flushed.
     */
    public function process($commit = false)
    {
        if ($this->output !== null) return $this->output;

        $this->output = [];

        // Created and updated can be figured out from the Excel file
        $tubes = [];
        for ($rowNumber = $this->startingRow; $rowNumber <= $this->worksheet->getNumRows(); $rowNumber++) {
            // If all values are blank assume it's just empty excel data
            if ($this->rowDataBlank($rowNumber)) continue;

            $rawAccessionId = $this->worksheet->getCellValue($rowNumber, $this->columnMap['accessionId']);

            // Validation methods return false if a field is invalid (and append to $this->messages)
            $rowOk = true;
            $rowOk = $this->validateAccessionId($rawAccessionId, $rowNumber) && $rowOk;

            // If any field failed validation do not import the row
            if (!$rowOk) continue;

            $tube = new Tube($rawAccessionId);

            $this->output[] = $tube;
            if ($commit) $this->em->persist($tube);

            $tubes[] = $tube;
        }

        return $tubes;
    }

    /**
     * Returns true if $raw is valid
     *
     * Otherwise, adds an error message to $this->messages and returns false
     */
    protected function validateAccessionId($raw, $rowNumber): bool
    {
        // Don't allow blank accession IDs
        if (!$raw) {
            $this->messages[] = ImportMessage::newError(
                'Accession ID cannot be blank or 0',
                $rowNumber,
                $this->columnMap['accessionId']
            );
            return false;
        }

        // Check length
        if (strlen($raw) > 255) {
            $this->messages[] = ImportMessage::newError(
                'Accession ID cannot be longer than 255 characters',
                $rowNumber,
                $this->columnMap['accessionId']
            );
            return false;
        }

        // Cannot already exist
        $repo = $this->em->getRepository(Tube::class);
        $exists = $repo->findOneBy(['accessionId' => $raw]);
        if ($exists) {
            $this->messages[] = ImportMessage::newError(
                sprintf('There is already a tube with accession ID "%s"', $raw),
                $rowNumber,
                $this->columnMap['accessionId']
            );
            return false;
        }

        return true;
    }
}

<?php


namespace App\ExcelImport;


use App\Entity\ExcelImportWorksheet;
use App\Entity\Tube;

class TubeImporter extends BaseExcelImporter
{
    public function __construct(ExcelImportWorksheet $worksheet)
    {
        parent::__construct($worksheet);

        $this->columnMap = [
            'accessionId' => 'A',
            'tubeType' => 'B',
            'kitType' => 'C',
        ];
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

        $this->output = [];

        // Created and updated can be figured out from the Excel file
        for ($rowNumber = $this->startingRow; $rowNumber <= $this->worksheet->getNumRows(); $rowNumber++) {
            $rawAccessionId = $this->worksheet->getCellValue($rowNumber, $this->columnMap['accessionId']);
            $rawTubeType = $this->worksheet->getCellValue($rowNumber, $this->columnMap['tubeType']);
            $rawKitType = $this->worksheet->getCellValue($rowNumber, $this->columnMap['kitType']);

            // Tube type should match constant, but not case sensitive
            $rawTubeType = strtoupper($rawTubeType);

            // Validation methods return false if a field is invalid (and append to $this->messages)
            $rowOk = true;
            $rowOk = $this->validateAccessionId($rawAccessionId, $rowNumber) && $rowOk;
            $rowOk = $this->validateTubeType($rawTubeType, $rowNumber) && $rowOk;
            $rowOk = $this->validateKitType($rawKitType, $rowNumber) && $rowOk;

            // If any field failed validation do not import the row
            if (!$rowOk) continue;

            $tube = new Tube($rawAccessionId);

            // Optional fields
            if ($rawTubeType) $tube->setTubeType($rawTubeType);
            if ($rawKitType) $tube->setKitType($rawKitType);

            $this->output[] = $tube;
            if ($commit) $this->em->persist($tube);
        }
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

    /**
     * Returns true if $raw is valid
     *
     * Otherwise, adds an error message to $this->messages and returns false
     */
    protected function validateTubeType($raw, $rowNumber): bool
    {
        // Optional field, so blank is OK
        if (!$raw) return true;

        if (!in_array($raw, Tube::getValidTubeTypes())) {
            $this->messages[] = ImportMessage::newError(
                sprintf('Tube type must be one of: %s', join(', ', Tube::getValidTubeTypes())),
                $rowNumber,
                $this->columnMap['tubeType']
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
    protected function validateKitType($raw, $rowNumber): bool
    {
        // Optional field, so blank is OK
        if (!$raw) return true;

        // Check length
        if (strlen($raw) > 255) {
            $this->messages[] = ImportMessage::newError(
                'Kit type cannot be longer than 255 characters',
                $rowNumber,
                $this->columnMap['kitType']
            );
            return false;
        }

        return true;
    }
}
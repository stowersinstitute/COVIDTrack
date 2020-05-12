<?php


namespace App\ExcelImport;


use App\Entity\ExcelImportWorksheet;
use App\Entity\ParticipantGroup;

class ParticipantGroupImporter extends BaseExcelImporter
{
    public function __construct(ExcelImportWorksheet $worksheet)
    {
        parent::__construct($worksheet);

        $this->columnMap = [
            'title' => 'A',
            'participantCount' => 'B',
        ];
    }

    /**
     * Processes the import
     *
     * Results will be stored in the $output property
     *
     * Messages (including errors) will be stored in the $messages property
     */
    public function process()
    {
        if ($this->output !== null) return $this->output;

        $participantGroups = [];

        // TODO: TEMPORARY, GENERATE REAL VALUE
        $groupIdx = date('ymdhis');

        for ($rowNumber = $this->startingRow; $rowNumber <= $this->worksheet->getNumRows(); $rowNumber++) {
            $rawTitle = $this->worksheet->getCellValue($rowNumber, $this->columnMap['title']);
            $rawParticipantCount = $this->worksheet->getCellValue($rowNumber, $this->columnMap['participantCount']);

            // Validation methods return false if a field is invalid (and append to $this->messages)
            $rowOk = true;
            $rowOk = $this->validateTitle($rawTitle, $rowNumber) && $rowOk;
            $rowOk = $this->validateParticipantCount($rawParticipantCount, $rowNumber) && $rowOk;

            // If any field failed validation do not import the row
            if (!$rowOk) continue;

            $group = new ParticipantGroup(
                'GRP-' . $groupIdx,
                $rawParticipantCount
            );

            $group->setTitle($rawTitle);

            $participantGroups[] = $group;

            $groupIdx++;
        }

        $this->output = $participantGroups;
    }

    /**
     * Returns true if $raw is a participant count
     *
     * Otherwise, adds an error message to $this->messages and returns false
     */
    protected function validateParticipantCount($raw, $rowNumber): bool
    {
        if (!$raw) {
            $this->messages[] = ImportMessage::newError(
                'Participant count cannot be blank or less than 1',
                $rowNumber,
                $this->columnMap['participantCount']
            );
            return false;
        }

        return true;
    }

    /**
     * Returns true if $raw is a valid title
     *
     * Otherwise, adds an error message to $this->messages and returns false
     */
    protected function validateTitle($raw, $rowNumber) : bool
    {
        if (!$raw) {
            $this->messages[] = ImportMessage::newError(
                'Title cannot be blank',
                $rowNumber,
                $this->columnMap['title']
            );
            return false;
        }

        // Title must contain only things that the barcode scanner can read
        $allowedSpecial = [' ', '-', '_'];
        for ($i=0; $i < strlen($raw); $i++) {
            $char = $raw[$i];

            if ($char >= 'a' && $char <= 'z') continue;
            if ($char >= 'A' && $char <= 'Z') continue;
            if ($char >= '0' && $char <= '9') continue;
            if (in_array($char, $allowedSpecial)) continue;

            // Not in the list of allowed characters
            $this->messages[] = ImportMessage::newError(
                sprintf('Invalid character: %s', $char),
                $rowNumber,
                $this->columnMap['title']
            );

            return false;
        }

        return true;
    }
}
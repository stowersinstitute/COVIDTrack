<?php


namespace App\ExcelImport;


use App\Entity\ExcelImportWorksheet;
use App\Entity\Tube;

class SpecimenIntakeImporter extends BaseExcelImporter
{
    public function __construct(ExcelImportWorksheet $worksheet)
    {
        parent::__construct($worksheet);

        $this->columnMap = [
            'tubeId' => 'A',
            'acceptedStatus' => 'B',
            'technicianUsername' => 'C',
        ];
    }

    /**
     * Applies the data in the excel file to existing entities
     *
     * Modified entities are returned
     */
    public function process($commit = false)
    {
        if ($this->output !== null) return $this->output;

        $seenTubes = [];

        for ($rowNumber=$this->startingRow; $rowNumber <= $this->worksheet->getNumRows(); $rowNumber++) {
            $tubeId = trim($this->worksheet->getCellValue($rowNumber, $this->columnMap['tubeId']));
            $rawAcceptedStatus = trim($this->worksheet->getCellValue($rowNumber, $this->columnMap['acceptedStatus']));
            $rawTechnicianUsername = trim($this->worksheet->getCellValue($rowNumber, $this->columnMap['technicianUsername']));

            // If all values are blank assume it's just empty excel data
            if ($this->rowDataBlank($rowNumber)) continue;

            $tube = $this->getTube($tubeId);
            // Immediately skip if there isn't a valid tube ID
            if (!$tube) {
                $details = sprintf('Tube ID "%s" does not exist', $tubeId);
                // Special case for an empty $tubeId
                if (!$tubeId) $details = 'Tube ID cannot be empty';
                $this->messages[] = ImportMessage::newError(
                    $details,
                    $rowNumber,
                    $this->columnMap['tubeId']
                );
                continue;
            }

            // Ensure that a modified entity won't be persisted unless we're importing for real (instead of previewing)
            if (!$commit) $this->em->detach($tube);

            // Validation methods return false if a field is invalid (and append to $this->messages)
            $rowOk = true;
            $rowOk = $this->validateTargetTube($tube, $rowNumber) && $rowOk;
            $rowOk = $this->validateAcceptOrReject($rawAcceptedStatus, $rowNumber) && $rowOk;
            $rowOk = $this->validateTechnicianUsername($rawTechnicianUsername, $rowNumber) && $rowOk;

            // If any field failed validation do not import the row
            if (!$rowOk) continue;

            $isAccepted = strtolower($rawAcceptedStatus) == 'accept';
            if ($isAccepted) {
                $tube->markAccepted($rawTechnicianUsername);
            }
            else {
                $tube->markRejected($rawTechnicianUsername);
            }

            $seenTubes[] = $tube;
        }

        // Commit changes to the database
        if ($commit) $this->em->flush();

        $this->output = $seenTubes;
    }

    protected function validateTargetTube(Tube $tube, $rowNumber) : bool
    {
        // todo: not sure any validation applies? If a tube shows up in the results but was never checked in is that really an error
        //          or should we just do our best to check it in and then audit log it?
        return true;

        // Tubes must be in "ACCEPTED" (next step is check in)
        if ($tube->getStatus() !== Tube::STATUS_ACCEPTED) {
            $this->messages[] = ImportMessage::newError(
                sprintf('Tube ID "%s" cannot be checked in because it has status %s', $tube->getAccessionId(), $tube->getStatusText()),
                $rowNumber,
                $this->columnMap['tubeId']
            );
            return false;
        }

        // todo: validate user-selected type is the same as in the excel file? Or just update and audit log?

        return true;
    }

    protected function validateAcceptOrReject($raw, $rowNumber) : bool
    {
        // Case-insensitive check to see if a valid accept/result is specified
        $validStatuses = ['accept', 'reject'];
        if (!in_array(strtolower($raw), $validStatuses)) {
            $this->messages[] = ImportMessage::newError(
                // todo: get real column name + statuses
                sprintf('Accept/Reject must be one of: %s', join(", ", $validStatuses)),
                $rowNumber,
                $this->columnMap['acceptedStatus']
            );
            return false;
        }

        return true;
    }

    protected function validateTechnicianUsername($raw, $rowNumber) : bool
    {
        if (!$raw) {
            $this->messages[] = ImportMessage::newError(
                sprintf('Technician username cannot be blank'),
                $rowNumber,
                $this->columnMap['technicianUsername']
            );
            return false;
        }

        $maxLen = 255; // From Tube::checkedInByUsername
        if (strlen($raw) > $maxLen) {
            $this->messages[] = ImportMessage::newError(
                sprintf('Technician username must be less than %s characters', $maxLen),
                $rowNumber,
                $this->columnMap['technicianUsername']
            );
            return false;
        }

        return true;
    }

    protected function getTube(string $tubeId) : ?Tube
    {
        return $this->em
            ->getRepository(Tube::class)
            ->findOneBy(['accessionId' => $tubeId]);
    }
}
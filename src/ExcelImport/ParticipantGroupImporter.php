<?php


namespace App\ExcelImport;


use App\Entity\ExcelImportWorksheet;
use App\Entity\ParticipantGroup;

class ParticipantGroupImporter
{
    /** @var ExcelImportWorksheet  */
    private $worksheet;

    /**
     * @var int Row to start import on
     */
    private $startingRow = 2; // header is row 1

    private $columnMap = [];

    public function __construct(ExcelImportWorksheet $worksheet)
    {
        $this->worksheet = $worksheet;

        $this->columnMap = [
            'accessionId' => 'A',
        ];
    }

    /**
     * @return ParticipantGroup[]
     */
    public function getParticipantGroups() : array
    {
        $participantGroups = [];

        for ($i=$this->startingRow; $i <= $this->worksheet->getNumRows(); $i++) {
            $participantGroups[] = new ParticipantGroup(
                $this->worksheet->getCellValue($i, $this->columnMap['accessionId']),
                0
            );
        }

        return $participantGroups;
    }
}
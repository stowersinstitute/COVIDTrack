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
            'title' => 'A',
            'participantCount' => 'B',
        ];
    }

    /**
     * @return ParticipantGroup[]
     */
    public function getParticipantGroups() : array
    {
        $participantGroups = [];

        // TODO: TEMPORARY, GENERATE REAL VALUE
        $groupIdx = date('ymdhis');

        for ($i=$this->startingRow; $i <= $this->worksheet->getNumRows(); $i++) {
            $rawParticipantCount = $this->worksheet->getCellValue($i, $this->columnMap['participantCount']);

            $group = new ParticipantGroup(
                'GRP-' . $groupIdx,
                $rawParticipantCount
            );

            dump($this->worksheet->getCellValue($i, $this->columnMap['participantCount']));
            $group->setTitle($this->worksheet->getCellValue($i, $this->columnMap['title']));

            $participantGroups[] = $group;

            $groupIdx++;
        }

        return $participantGroups;
    }
}
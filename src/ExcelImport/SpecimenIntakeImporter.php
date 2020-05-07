<?php


namespace App\ExcelImport;


use App\Entity\ExcelImportWorksheet;
use App\Entity\Tube;
use Doctrine\ORM\EntityManager;

class SpecimenIntakeImporter
{
    /** @var ExcelImportWorksheet  */
    private $worksheet;

    /** @var EntityManager */
    private $em;

    /**
     * @var int Row to start import on
     */
    private $startingRow = 2; // header is row 1

    private $columnMap = [];

    public function __construct(ExcelImportWorksheet $worksheet, EntityManager $em)
    {
        $this->worksheet = $worksheet;
        $this->em = $em;

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
        $seenTubes = [];

        for ($i=$this->startingRow; $i <= $this->worksheet->getNumRows(); $i++) {
            $tubeId = trim($this->worksheet->getCellValue($i, $this->columnMap['tubeId']));
            $rawAcceptedStatus = trim($this->worksheet->getCellValue($i, $this->columnMap['acceptedStatus']));
            $rawTechnicianUsername = trim($this->worksheet->getCellValue($i, $this->columnMap['technicianUsername']));

            $isAccepted = strtolower($rawAcceptedStatus) == 'accept';

            $tube = $this->getTube($tubeId);
            // Ensure that a modified entity won't be persisted unless we're importing for real (instead of previewing)
            if (!$commit) $this->em->detach($tube);

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

        return $seenTubes;
    }

    protected function getTube(string $tubeId) : Tube
    {
        return $this->em
            ->getRepository(Tube::class)
            ->findOneBy(['accessionId' => $tubeId]);
    }
}
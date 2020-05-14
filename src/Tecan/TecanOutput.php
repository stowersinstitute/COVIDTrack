<?php

namespace App\Tecan;

use App\Repository\TubeRepository;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class TecanOutput
{
    /**
     * Version displaying in output file when this parser was last updated.
     */
    const OUTPUT_PARSER_VERSION = 'Version 2, 7, 30, 0';

    /**
     * First row with Tube data.
     * File has two rows of header data.
     * @var int
     */
    const FIRST_DATA_ROW = 3;

    /**
     * Number of expected rows to find Tube data.
     */
    const NUM_TUBE_ROWS = 96;

    /**
     * Column Letter where Tube IDs expected.
     */
    const TUBE_ID_COLUMN_LETTER = 'F';

    /**
     * Column text where Tube IDs expected.
     */
    const TUBE_ID_COLUMN_LABEL = 'SRCTubeID';

    /**
     * @var \PhpOffice\PhpSpreadsheet\Spreadsheet
     */
    private $workbook;

    /**
     * Version string parsed from file.
     * @var string
     */
    private $version;

    /**
     * @var string
     */
    private $wellPlateId;

    public function __construct(string $filepath)
    {
        $this->workbook = IOFactory::load($filepath);

        // Backup to remove readonly flag
//        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filepath);
//        $reader->setReadDataOnly(true);
//        $reader->load($filepath);

//        $this->worksheet = $this->workbook->getSheet($this->workbook->getFirstSheetIndex());

        $this->parseVersion();
        $this->parseWellPlateId();
    }

    /**
     * Create TecanOutput using the uploaded file received through HTTP request.
     *
     * @return TecanOutput
     */
    public static function fromUploadFile(UploadedFile $file): self
    {
        return new static($file->getRealPath());
    }

    /**
     * Copies original input file and writes a new file with the Tube Accession IDs
     * replaced with Specimen Accession IDs.
     *
     * @param string $exportFilePath Path including filename where output written
     * @return string Path where output file written
     */
    public function convertTubesToSpecimens(TubeRepository $tubeRepo, string $exportFilePath): string
    {
        // Copy Workbook because we'll be modifying cells
        $exportWB = clone $this->workbook;

        // Work on the only sheet in the workbook
        $worksheet = $exportWB->getActiveSheet();

        // Verify Tube IDs are in expected column before we start parsing
        $foundColumnLabel = $worksheet->getCell(self::TUBE_ID_COLUMN_LETTER.'1')->getValue();
        if ($foundColumnLabel !== self::TUBE_ID_COLUMN_LABEL) {
            $msg = sprintf(
                'Expected to find Tube IDs in column %s with column label "%s" but found column label "%s"',
                self::TUBE_ID_COLUMN_LETTER,
                self::TUBE_ID_COLUMN_LABEL,
                $foundColumnLabel
            );
            throw new \RuntimeException($msg);
        }

        // Loop through Tube ID cells
        // Convert Tube Accession ID to Specimen Accession ID
        for ($i=self::FIRST_DATA_ROW; $i<=self::NUM_TUBE_ROWS; $i++) {
            // For example: F3
            $coordinate = sprintf('%s%d', self::TUBE_ID_COLUMN_LETTER, $i);
            $cell = $worksheet->getCell($coordinate);

            // Read Tube ID
            $tubeId = $cell->getValue();
            if (!$tubeId) {
                // Cell does not contain a Tube ID
                continue;
            }

            // Find Specimen ID in database
            $specimenId = $tubeRepo->findSpecimenAccessionIdByTubeAccessionId($tubeId);
            if (!$specimenId) {
                // Cell value (Tube ID) did not map to a Specimen ID
                continue;
            }

            // Write Specimen ID in cell where Tube ID came from
            $cell->setValue($specimenId);
        }

        // Write export data to file
        $outputFileExt = strtolower(pathinfo($exportFilePath, PATHINFO_EXTENSION));
        switch ($outputFileExt) {
            case 'xlsx':
                $writerType = "Xlsx";
                break;
            case 'xls':
            default:
                $writerType = "Xls";
                break;
        }
        $writer = IOFactory::createWriter($exportWB, $writerType);
        $writer->save($exportFilePath);

        return $exportFilePath;
    }

    private function parseWellPlateId()
    {
        // TODO: Cell I2
    }

    private function parseVersion()
    {
        // TODO: Cell B2
        // TODO: Warning if parser and upload file don't match?
    }
}

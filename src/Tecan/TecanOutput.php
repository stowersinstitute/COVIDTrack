<?php

namespace App\Tecan;

use App\Repository\TubeRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class TecanOutput
{
    /**
     * Text lines of original input file. Each array value is a text line.
     *
     * @var array[]
     */
    private $inputLines;

    public function __construct(string $filepath)
    {
        $this->inputLines = file($filepath);
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
        /** @var string[] $output */
        $output = [];

        foreach ($this->inputLines as $line) {
            // Pattern matches: (tab)T00001234
            preg_match("/\t(T\d{8})/", $line, $matches);

            // If line contains a Tube ID
            if (!empty($matches)) {
                // Begin replacing Tube ID with Specimen ID
                $tubeId = $matches[1];

                // If Specimen ID found
                $specimenId = $tubeRepo->findSpecimenAccessionIdByTubeAccessionId($tubeId);
                if ($specimenId) {
                    // Replace Tube ID with Specimen ID
                    $line = str_replace($tubeId, $specimenId, $line);
                }
            }

            $output[] = $line;
        }

        // Write to tmp path
        $success = file_put_contents($exportFilePath, $output);
        if (!$success) {
            throw new \RuntimeException('Could not write Tube ID conversion temp file');
        }

        return $exportFilePath;
    }
}

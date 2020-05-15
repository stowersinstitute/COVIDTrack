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
     * Find and replace Tube Accession IDs with Specimen Accession IDs.
     *
     * @return string[] Input file lines with Tube IDs replaced. Each line contains original line ending.
     */
    public function convertTubesToSpecimens(TubeRepository $tubeRepo): array
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

        return $output;
    }
}

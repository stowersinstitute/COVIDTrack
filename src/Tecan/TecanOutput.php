<?php

namespace App\Tecan;

use App\Repository\TubeRepository;

/**
 * Class for working with file output from Tecan hardware.
 *
 * For example original file output, see src/Resources/RPE1P7.XLS
 * which is a tab-delimited file, but uses .XLS for easy opening in Excel on Desktop.
 */
class TecanOutput
{
    /**
     * Find and replace Tube Accession IDs with Specimen Accession IDs
     * in the given file.
     *
     * @return string Returns given file with text replaced.
     */
    public static function convertTubesToSpecimens(string $inputFilePath, TubeRepository $tubeRepo): string
    {
        // Text of original input file
        $fileAsString = file_get_contents($inputFilePath);
        if (!$fileAsString) {
            throw new \RuntimeException('Cannot read Tecan file');
        }

        // Parse Tube Accession IDs from spreadsheet
        $rawTubeAccessionIds = TecanImporter::getRawTubeAccessionIds($inputFilePath);
        foreach ($rawTubeAccessionIds as $rawTubeAccessionId) {
            $tube = $tubeRepo->findOneWithSpecimenLoaded($rawTubeAccessionId);

            if (!$tube && !$tube->getSpecimen()) {
                throw new \InvalidArgumentException(sprintf('Cannot find Tube for Tube Accession ID "%s"', $rawTubeAccessionId ));
            }

            // Replace Tube ID with Specimen ID anywhere in file
            $tubeSearch = $tube->getAccessionId();
            $specimenReplace = $tube->getSpecimen()->getAccessionId();
            $fileAsString = str_replace($tubeSearch, $specimenReplace, $fileAsString);
        }

        return $fileAsString;
    }
}

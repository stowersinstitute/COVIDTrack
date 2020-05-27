<?php

namespace App\ExcelImport;

use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Exception;

/**
 * Custom Reader for PhpSpreadsheet library to parse Tecan output file.
 *
 * The Tecan output file uses tab-delimited data but uses .XLS file extension
 * for easy opening in Excel. PhpSpreadsheet's Csv Reader supports tab-delimited
 * reading but was too aggressive with checking whether it could read the file.
 * This Reader explicitly supports the Tecan file and its internal structure.
 */
class TecanOutputReader extends Csv
{
    public function __construct()
    {
        parent::__construct();

        // Tab-delimited
        $this->setDelimiter("\t");
    }

    /**
     * OVERRIDDEN to remove file extension guessing and more supported mime-types.
     *
     * Can the current IReader read the file?
     *
     * @param string $filepath
     * @return bool
     */
    public function canRead($filepath)
    {
        // Check if file exists and is readable
        try {
            $this->openFile($filepath);
        } catch (Exception $e) {
            return false;
        } finally {
            // Close open file handler from $this->openFile()
            fclose($this->fileHandle);
        }

        // Attempt to guess mime-type (requires PHP ext-fileinfo)
        if (function_exists('mime_content_type')) {
            $type = mime_content_type($filepath);
            $supportedTypes = [
                // START parent mime-types
                'text/csv',
                'text/plain',
                'inode/x-empty',
                // END parent mime-types

                // Custom mime-types
                'application/octet-stream', // type for Tecan output
            ];

            return in_array($type, $supportedTypes, true);
        }

        // Something is wrong, we need to add explicit programming support
        // for this file type before blindly assuming it will work
        return false;
    }
}

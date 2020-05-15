<?php

namespace App\Tecan;

/**
 * Thrown when cannot find a Specimen Accession ID for given Tube Accession ID
 * found in Tecan output file.
 */
class SpecimenIdNotFoundException extends \RuntimeException
{
}

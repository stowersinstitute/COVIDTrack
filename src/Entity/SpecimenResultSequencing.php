<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Result of sequencing SARS-CoV-2 virus found in Specimen.
 *
 * @ORM\Entity
 * NOTE: (a)ORM\Table defined on parent class
 */
class SpecimenResultSequencing extends SpecimenResult
{
}

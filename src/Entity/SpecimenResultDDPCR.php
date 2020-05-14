<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Result of performing ddPCR analysis on Specimen.
 *
 * @ORM\Entity
 * NOTE: (a)ORM\Table defined on parent class
 */
class SpecimenResultDDPCR extends SpecimenResult
{
}

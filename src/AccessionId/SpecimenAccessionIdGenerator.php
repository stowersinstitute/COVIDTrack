<?php

namespace App\AccessionId;

use App\Entity\Specimen;
use App\Entity\SpecimenRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Generates unique Specimen Accession ID
 */
class SpecimenAccessionIdGenerator
{
    /**
     * @var SpecimenRepository
     */
    private $specimenRepo;

    public function __construct(EntityManagerInterface $em)
    {
        $this->specimenRepo = $em->getRepository(Specimen::class);
    }

    /**
     * Generate a unique Specimen Accession ID.
     */
    public function generate(): string
    {
        // TODO: CVDLS-30 Support creating unique accession ID when creating
        // Generate new ID until finding an unused one
        do {
            $id = 'CID' . mt_rand(100000, 9999999);
        } while ($this->specimenRepo->findOneByAnyId($id));

        return $id;
    }
}

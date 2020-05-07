<?php

namespace App\AccessionId;

use App\Entity\SpecimenRepository;

/**
 * Generates unique Specimen Accession ID
 */
class SpecimenAccessionIdGenerator
{
    /**
     * @var SpecimenRepository
     */
    private $specimenRepo;

    public function __construct(SpecimenRepository $repo)
    {
        $this->specimenRepo = $repo;
    }

    /**
     * Generate a unique Specimen Accession ID.
     */
    public function generate(): string
    {
        // TODO: CVDLS-30 Support creating unique accession ID when creating
        do {
            $id = 'CID' . mt_rand(100000, 9999999);
        } while (!$this->specimenRepo->findOneByAnyId($id));

        return $id;
    }
}

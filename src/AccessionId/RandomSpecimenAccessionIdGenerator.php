<?php

namespace App\AccessionId;

use App\Entity\Specimen;
use App\Entity\SpecimenRepository;
use App\Util\StringUtils;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Generates unique Specimen Accession ID by generating a random value
 */
class RandomSpecimenAccessionIdGenerator
{
    /**
     * @var SpecimenRepository
     */
    private $repository;

    /**
     * @var string[] IDs generated during the lifetime of this object
     */
    private $generatedThisInstance = [];

    public function __construct(EntityManagerInterface $em)
    {
        $this->repository = $em->getRepository(Specimen::class);
    }

    /**
     * Generate a unique Specimen Accession ID not currently used
     */
    public function generate(): string
    {
        // Sanity check to prevent infinite loop
        $maxTries = 1000;

        $id = null;

        do {
            // Accumulate from previous loop, if exists
            if ($id) {
                $this->generatedThisInstance[] = $id;
            }

            // Generate a new ID
            $id = sprintf('C' . StringUtils::generateRandomString(8, true));

            $maxTries--;
        } while ($this->idExists($id) && $maxTries > 0);

        if ($maxTries === 0) throw new \ErrorException('Unable to generate a Specimen ID (exceeded max tries)');

        return $id;
    }

    private function idExists(string $id): bool
    {
        // Consider it existing if we've generated it already.
        // This makes working with unpersisted entities easier.
        if (in_array($id, $this->generatedThisInstance)) return true;

        // ID exists if it's attached to an existing record in the database
        $found = $this->repository->findOneBy(['accessionId' => $id]);

        return $found ? true : false;
    }
}

<?php


namespace App\Entity;


use Doctrine\ORM\EntityRepository;

/**
 * Convenience methods for querying system configuration stored in the database
 *
 * @deprecated Consider using the AppConfiguration service since it has caching and may be
 *  aware of more settings
 */
class SystemConfigurationEntryRepository extends EntityRepository
{
    public function findOneByReferenceId(string $referenceId) : ?SystemConfigurationEntry
    {
        return $this->findOneBy(['referenceId' => $referenceId]);
    }
}
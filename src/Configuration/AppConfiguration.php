<?php

namespace App\Configuration;

use App\Entity\SystemConfigurationEntry;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service to read application settings
 *
 * If an EntityManagerInterface is provided settings will be read and written
 * to the database.
 */
class AppConfiguration
{
    /**
     * Persists config to the database. Service is optional for a few reasons:
     *
     * - Makes testing a lot easier.
     * - This service could be used for initial application setup before the
     *   database connection information is defined.
     *
     * @var null|EntityManagerInterface
     */
    protected $em;

    /**
     * Cache of settings to reduce database interactions.
     *
     * @var mixed[]
     */
    protected $cache = [];

    /**
     * When true, configuration changes are immediately committed to the
     * database with flush().
     *
     * When false, application code must explicitly call EM->flush().
     *
     * @var bool
     */
    protected $autoFlush = true;

    public function __construct(EntityManagerInterface $em = null)
    {
        $this->em = $em;
    }

    public function hasReferenceId(string $referenceId) : bool
    {
        // Early return if we know the key exists, but if it doesn't we may still need to check the database
        if (array_key_exists($referenceId, $this->cache)) return true;

        if ($this->em) {
            $entry = $this->getRepository()->findOneByReferenceId($referenceId);
            if ($entry) {
                $this->cache[$referenceId] = $entry->getValue();
            }
        }

        return array_key_exists($referenceId, $this->cache);
    }

    public function set(string $referenceId, $value, string $label = null)
    {
        $this->cache[$referenceId] = $value;

        if ($this->em) {
            $entry = $this->getRepository()->findOneByReferenceId($referenceId);
            if (!$entry) {
                $entry = new SystemConfigurationEntry($referenceId);
                $this->em->persist($entry);
            }

            if (null !== $label) {
                $entry->setLabel($label);
            }

            $entry->setValue($value);

            if ($this->autoFlush) $this->em->flush();
        }
    }

    /**
     * This method throws an exception if $referenceId is already defined
     */
    public function create(string $referenceId, $value, string $label = null)
    {
        if ($this->hasReferenceId($referenceId)) throw new \InvalidArgumentException(sprintf('referenceId "%s" already exists', $referenceId));

        $entry = new SystemConfigurationEntry($referenceId, $label);
        $entry->setValue($value);

        $this->cache[$referenceId] = $entry->getValue();

        if ($this->em) {
            $this->em->persist($entry);
            if ($this->autoFlush) $this->em->flush();
        }
    }

    /**
     * Returns the value for $reference ID or null if it doesn't exist
     *
     * If you need to check whether a setting exists, see hasReferenceId()
     *
     * @return mixed|null
     */
    public function get(string $referenceId)
    {
        if (array_key_exists($referenceId, $this->cache)) {
            return $this->cache[$referenceId];
        }

        if ($this->em) {
            $entry = $this->getRepository()->findOneByReferenceId($referenceId);
            if (!$entry) return null;
            $this->cache[$referenceId] = $entry->getValue();
        }

        return $this->cache[$referenceId] ?? null;
    }

    protected function getRepository()
    {
        return $this->em->getRepository(SystemConfigurationEntry::class);
    }

    public function getAutoFlush(): bool
    {
        return $this->autoFlush;
    }

    public function setAutoFlush(bool $autoFlush): void
    {
        $this->autoFlush = $autoFlush;
    }
}
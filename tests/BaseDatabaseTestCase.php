<?php

namespace App\Tests;

use Liip\TestFixturesBundle\Test\FixturesTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Extend this class for writing PHPUnit tests that utilize Doctrine and
 * a database with test data(SQLite).
 *
 * The test database is isolated from the dev environment database, and is
 * reset at the end of each test method.
 *
 * Fetch EntityManager using $this->em.
 *
 * Each test case can load its own set of fixtures.:
 *
 *     public function testSomeFixturesLoad()
 *     {
 *         $this->loadFixtures([
 *             TubeFixturesForTesting::class, // Load 5 Tube records
 *         ]);
 *
 *         $tubes = $this->em->getRepository(EntityLoadedInFixtures::class)->findAll();
 *         $this->assertCount(5, $tubes); // All Tube records from TubeFixturesForTesting
 *     }
 *
 */
abstract class BaseDatabaseTestCase extends KernelTestCase
{
    use FixturesTrait;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    protected function setUp()
    {
        parent::setUp();

        $this->em = $this->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    /**
     * Persist/Flush multiple entities.
     *
     * Usage:
     *     $this->persistAndFlush($one, $two, $three);
     */
    protected function persistAndFlush(...$entities)
    {
        foreach ($entities as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
    }

    protected function tearDown()
    {
        parent::tearDown();

        // To avoid memory leaks
        $this->em->close();
        $this->em = null;
    }
}

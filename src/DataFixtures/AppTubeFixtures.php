<?php

namespace App\DataFixtures;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Entity\DropOff;
use App\Entity\ParticipantGroup;
use App\Entity\Tube;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

class AppTubeFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * @var ParticipantGroup[]
     */
    private $allGroups;

    public function getDependencies()
    {
        return [
            AppParticipantGroupsFixtures::class,
        ];
    }

    /**
     * Generates Species ID
     *
     * @var SpecimenAccessionIdGenerator
     */
    private $speciesAccessionIdGen;

    public function __construct(SpecimenAccessionIdGenerator $gen)
    {
        $this->speciesAccessionIdGen = $gen;
    }

    public function load(ObjectManager $em)
    {
        $this->distributedTubes($em);
        $this->returnedTubes($em);
        $this->acceptedTubes($em);
        $this->rejectedTubes($em);
        $this->tubesForTecanExample($em);

        $em->flush();
    }

    /**
     * Tubes that have their label printed and distributed.
     */
    private function distributedTubes(ObjectManager $em)
    {
        $startAccession = 1000;

        $numToCreate = 20;
        for ($i=1; $i<= $numToCreate; $i++) {
            $accessionId = 'TUBE-' . ($i+$startAccession);

            $T = new Tube($accessionId);

            $em->persist($T);
        }
    }

    /**
     * Tubes that Participants have returned at a Kiosk and are ready for
     * Technician check-in.
     */
    private function returnedTubes(ObjectManager $em)
    {
        $startAccession = 2000;

        $numToCreate = 50;
        for ($i=1; $i<= $numToCreate; $i++) {
            $accessionId = 'TUBE-' . ($i+$startAccession);

            $T = new Tube($accessionId);

            // Tube Specimens will have been collected (extracted) from the
            // Participant within the last few days
            $collectedAt = new \DateTimeImmutable(sprintf('-%d days 9:00am', $i%14));
            $this->doKioskDropoff($em, $T, $collectedAt);

            $em->persist($T);
        }
    }

    /**
     * Tubes that have been checked-in by a Tech.
     *
     * These Tubes are ready to have Results made.
     */
    private function acceptedTubes(ObjectManager $em)
    {
        $startAccession = 3000;

        $numToCreate = 25;
        $checkedInBy = 'test-checkin-user';
        for ($i=1; $i<= $numToCreate; $i++) {
            $accessionId = 'TUBE-' . ($i+$startAccession);

            $T = new Tube($accessionId);

            $collectedAt = new \DateTimeImmutable(sprintf('-%d days 9:00am', $i%7));
            $this->doKioskDropoff($em, $T, $collectedAt);

            $T->markAccepted($checkedInBy, new \DateTimeImmutable(sprintf('-%d days 10:00am', $i%7)));

            $em->persist($T);
        }
    }

    /**
     * Tubes that have been rejected.
     */
    private function rejectedTubes(ObjectManager $em)
    {
        $startAccession = 4000;

        $numToCreate = 10;
        $checkedInBy = 'test-checkin-user';
        for ($i=1; $i<= $numToCreate; $i++) {
            $accessionId = 'TUBE-' . ($i+$startAccession);

            $T = new Tube($accessionId);

            $collectedAt = new \DateTimeImmutable(sprintf('-%d days 9:00am', $i%7));
            $this->doKioskDropoff($em, $T, $collectedAt);

            $T->markRejected($checkedInBy, new \DateTimeImmutable(sprintf('-%d days 10:00am', $i%7)));

            $em->persist($T);
        }
    }

    private function getRandomGroup(ObjectManager $em): ParticipantGroup
    {
        if (empty($this->allGroups)) {
            $this->allGroups = $em->getRepository(ParticipantGroup::class)->findAll();
        }

        return $this->allGroups[array_rand($this->allGroups)];
    }

    /**
     * All the work to simulate a kiosk dropoff.
     */
    private function doKioskDropoff(ObjectManager $em, Tube $T, \DateTimeInterface $collectedAt)
    {
        // Assume 1 tube per dropoff
        $dropoff = new DropOff();
        $em->persist($dropoff);

        $group = $this->getRandomGroup($em);

        $possibleTubeTypes = Tube::getValidTubeTypes();
        $tubeType = $possibleTubeTypes[array_rand($possibleTubeTypes)];

        $T->kioskDropoff($this->speciesAccessionIdGen, $dropoff, $group, $tubeType, $collectedAt);
    }

    private function tubesForTecanExample(ObjectManager $em)
    {
        $repo = $em->getRepository(Tube::class);
        foreach(range(122, 217) as $i) {
            $accessionId = sprintf("T00000%d", $i);

            $found = $repo->findOneBy(['accessionId' => $accessionId]);
            if (!$found) {
                // Create
                $T = new Tube($accessionId);

                // Drop-off
                $collectedAt = new \DateTimeImmutable(sprintf('-%d days 9:00am', 1));
                $this->doKioskDropoff($em, $T, $collectedAt);

                // Accepted
                $T->markAccepted('fixtures', new \DateTimeImmutable(sprintf('-%d days 10:00am', 1)));

                $em->persist($T);
            }
        }
    }
}

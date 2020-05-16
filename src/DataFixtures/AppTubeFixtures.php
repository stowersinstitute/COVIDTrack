<?php

namespace App\DataFixtures;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\AccessionId\TubeAccessionIdGenerator;
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
     * Generates Tube Accession IDs
     *
     * @var TubeAccessionIdGenerator
     */
    private $tubeAccessionIdGen;

    /**
     * Generates Species Accession IDs
     *
     * @var SpecimenAccessionIdGenerator
     */
    private $speciesAccessionIdGen;

    public function __construct(TubeAccessionIdGenerator $tubeIdGen, SpecimenAccessionIdGenerator $specIdGen)
    {
        $this->tubeAccessionIdGen = $tubeIdGen;
        $this->speciesAccessionIdGen = $specIdGen;
    }

    public function load(ObjectManager $em)
    {
        $this->tubesForTecanExample($em);
        $this->distributedTubes($em);
        $this->returnedTubes($em);
        $this->acceptedTubes($em);
        $this->rejectedTubes($em);

        $em->flush();
    }

    /**
     * Tubes that have their label printed and distributed.
     */
    private function distributedTubes(ObjectManager $em)
    {
        $numToCreate = 20;
        for ($i=1; $i<= $numToCreate; $i++) {
            $T = Tube::create($this->tubeAccessionIdGen);

            $em->persist($T);
        }
    }

    /**
     * Tubes that Participants have returned at a Kiosk and are ready for
     * Technician check-in.
     */
    private function returnedTubes(ObjectManager $em)
    {
        $numToCreate = 50;
        for ($i=1; $i<= $numToCreate; $i++) {
            $T = Tube::create($this->tubeAccessionIdGen);

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
        $numToCreate = 25;
        $checkedInBy = 'test-checkin-user';
        for ($i=1; $i<= $numToCreate; $i++) {
            $T = Tube::create($this->tubeAccessionIdGen);

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
        $numToCreate = 10;
        $checkedInBy = 'test-checkin-user';
        for ($i=1; $i<= $numToCreate; $i++) {
            $T = Tube::create($this->tubeAccessionIdGen);

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

    /**
     * Ensure fixture Tubes exist that match Tube IDs in example Tecan output
     * file at src/Resources/RPE1P7.XLS.
     */
    private function tubesForTecanExample(ObjectManager $em)
    {
        $repo = $em->getRepository(Tube::class);
        foreach(range(122, 217) as $i) {
            $accessionId = sprintf("T00000%d", $i);

            $found = $repo->findOneBy(['accessionId' => $accessionId]);
            if (!$found) {
                // Create with hardcoded Tube Accession ID
                $T = new Tube($accessionId);

                // Drop-off
                $collectedAt = new \DateTimeImmutable(sprintf('-%d days 9:00am', 1));
                $this->doKioskDropoff($em, $T, $collectedAt);

                // Accepted
                $T->markAccepted('fixtures', new \DateTimeImmutable(sprintf('-%d days 10:00am', 1)));

                $em->persist($T);
            }
        }

        // These Tube IDs must exist so remaining fixtures are generated
        // with higher Accession IDs
        $em->flush();
    }
}

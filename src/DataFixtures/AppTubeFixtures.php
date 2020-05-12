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
        $this->droppedOffTubes($em);
        $this->acceptedTubes($em);
        $this->rejectedTubes($em);

        $em->flush();
    }

    /**
     * Tubes that have their label printed and distributed.
     */
    private function distributedTubes(ObjectManager $em)
    {
        $startAccession = 100;

        for ($i=1; $i<=20; $i++) {
            $accessionId = 'TUBE-' . ($i+$startAccession);

            $T = new Tube($accessionId);

            $em->persist($T);
        }
    }

    /**
     * Tubes that Participants have returned at a Kiosk.
     */
    private function droppedOffTubes(ObjectManager $em)
    {
        $startAccession = 200;

        for ($i=1; $i<=50; $i++) {
            $accessionId = 'TUBE-' . ($i+$startAccession);

            $T = new Tube($accessionId);

            $collectedAt = new \DateTimeImmutable(sprintf('-%d days 9:00am', $i));
            $this->doKioskDropoff($em, $T, $collectedAt);

            $em->persist($T);
        }
    }

    /**
     * Tubes that have been checked in by a tech.
     */
    private function acceptedTubes(ObjectManager $em)
    {
        $startAccession = 300;

        $numToCreate = 20;
        $checkedInBy = 'test-checkin-user';
        for ($i=1; $i<= $numToCreate; $i++) {
            $accessionId = 'TUBE-' . ($i+$startAccession);

            $T = new Tube($accessionId);

            $collectedAt = new \DateTimeImmutable(sprintf('-%d days 9:00am', $i));
            $this->doKioskDropoff($em, $T, $collectedAt);

            // TODO: CVDLS-39 This probably needs a Specimen but John will work that out later
            $T->markAccepted($checkedInBy, new \DateTimeImmutable(sprintf('-%d days 10:00am', $i)));

            $em->persist($T);
        }
    }

    /**
     * Tubes that have been rejected.
     */
    private function rejectedTubes(ObjectManager $em)
    {
        $startAccession = 400;

        $numToCreate = 10;
        $checkedInBy = 'test-checkin-user';
        for ($i=1; $i<= $numToCreate; $i++) {
            $accessionId = 'TUBE-' . ($i+$startAccession);

            $T = new Tube($accessionId);

            $collectedAt = new \DateTimeImmutable(sprintf('-%d days 9:00am', $i));
            $this->doKioskDropoff($em, $T, $collectedAt);

            $T->markRejected($checkedInBy, new \DateTimeImmutable(sprintf('-%d days 10:00am', $i)));

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
}

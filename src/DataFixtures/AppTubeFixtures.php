<?php

namespace App\DataFixtures;

use App\Entity\Tube;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class AppTubeFixtures extends Fixture
{
    public function load(ObjectManager $em)
    {
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
    private function returnedTubes(ObjectManager $em)
    {
        $startAccession = 200;

        for ($i=1; $i<=20; $i++) {
            $accessionId = 'TUBE-' . ($i+$startAccession);

            $T = new Tube($accessionId);
            $T->markReturned(new \DateTimeImmutable(sprintf('-%d days 9:00am', $i)));

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
            $T->markReturned(new \DateTimeImmutable(sprintf('-%d days 9:00am', $i)));
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
            $T->markReturned(new \DateTimeImmutable(sprintf('-%d days 9:00am', $i)));
            $T->markRejected($checkedInBy, new \DateTimeImmutable(sprintf('-%d days 10:00am', $i)));

            $em->persist($T);
        }
    }
}

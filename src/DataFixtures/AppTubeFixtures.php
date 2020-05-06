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
}

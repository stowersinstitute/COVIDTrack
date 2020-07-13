<?php

namespace App\DataFixtures;

use App\Entity\Tube;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Tubes received from a third-party vendor that come pre-labeled.
 */
class AppPreLabeledTubeFixtures extends Fixture
{
    public function load(ObjectManager $em)
    {
        $numToCreate = 50;
        for ($i = 1; $i <= $numToCreate; $i++) {
            $number = str_pad($i, 4, '0', STR_PAD_LEFT);
            $tube = new Tube('PRELABEL' . $number);

            $em->persist($tube);
        }

        $em->flush();
    }
}

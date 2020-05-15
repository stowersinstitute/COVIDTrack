<?php


namespace App\DataFixtures;


use App\Entity\Kiosk;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Physical kiosks
 */
class AppKioskFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
        foreach ($this->getData() as $raw) {
            $kiosk = new Kiosk($raw['label']);

            $kiosk->setLocation($raw['location'] ?? null);

            $manager->persist($kiosk);
        }

        $manager->flush();
    }

    protected function getData()
    {
        return [
            [ 'label' => 'Kiosk One',   'location' => 'Main Entry', ],
        ];
    }
}
<?php

namespace App\DataFixtures;

use App\Entity\WellPlate;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class AppWellPlateFixtures extends Fixture
{
    public function load(ObjectManager $em)
    {
        foreach ($this->getData() as $raw) {
            $p = new WellPlate($raw['barcode']);

            // wellPlate.VIRALPLATE1
            // wellPlate.BLOODPLATE1
            $referenceId = 'wellPlate.' . $p->getBarcode();
            $this->addReference($referenceId, $p);

            $em->persist($p);
        }

        $em->flush();
    }

    private function getData(): array
    {
        return [
            [
                'barcode' => 'VIRALPLATE1',
            ],
            [
                'barcode' => 'VIRALPLATE2',
            ],
            [
                'barcode' => 'VIRALPLATE3',
            ],
            [
                'barcode' => 'VIRALPLATE4',
            ],
            [
                'barcode' => 'VIRALPLATE5',
            ],
            [
                'barcode' => 'BLOODPLATE1',
            ],
            [
                'barcode' => 'BLOODPLATE2',
            ],
            [
                'barcode' => 'BLOODPLATE3',
            ],
            [
                'barcode' => 'BLOODPLATE4',
            ],
            [
                'barcode' => 'BLOODPLATE5',
            ],
        ];
    }
}

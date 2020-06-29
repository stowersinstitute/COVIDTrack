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

            // wellPlate.FIXPLATE1
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
                'barcode' => 'FIXPLATE1',
            ],
            [
                'barcode' => 'FIXPLATE2',
            ],
            [
                'barcode' => 'FIXPLATE3',
            ],
            [
                'barcode' => 'FIXPLATE4',
            ],
            [
                'barcode' => 'FIXPLATE5',
            ],
        ];
    }
}

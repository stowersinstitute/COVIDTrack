<?php

namespace App\DataFixtures;

use App\Entity\Specimen;
use App\Entity\WellPlate;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

class AppWellPlateFixtures extends Fixture implements DependentFixtureInterface
{
    const PLATE_ONE_BARCODE = 'ABC101';
    const PLATE_TWO_BARCODE = 'ABC102';
    const PLATE_THREE_BARCODE = 'ABC103';
    const PLATE_FOUR_BARCODE = 'ABC104';
    const PLATE_FIVE_BARCODE = 'ABC105';

    /**
     * @var null|Specimen[]
     */
    private $storableSpecimens;

    public function getDependencies()
    {
        return [
            AppTubeFixtures::class,
        ];
    }

    public function load(ObjectManager $em)
    {
        $this->createWellPlates($em);
        $this->addSpecimens($em);

        $em->flush();
    }

    private function createWellPlates(ObjectManager $em)
    {
        foreach ($this->getWellPlateData() as $data) {
            $plate = new WellPlate();
            $plate->setBarcode($data['barcode']);

            $this->setReference('wellPlate.' . $data['barcode'], $plate);

            $em->persist($plate);
        }
    }

    private function getWellPlateData()
    {
        return [
            ['barcode' => self::PLATE_ONE_BARCODE],
            ['barcode' => self::PLATE_TWO_BARCODE],
            ['barcode' => self::PLATE_THREE_BARCODE],
            ['barcode' => self::PLATE_FOUR_BARCODE],
            ['barcode' => self::PLATE_FIVE_BARCODE],
        ];
    }

    private function addSpecimens(ObjectManager $em)
    {
        foreach ($this->getWellPlateAndPositions() as $data) {
            $specimen = $this->getRandomStorableSpecimen($em);

            /** @var WellPlate $plate */
            $plate = $this->getReference('wellPlate.' . $data['barcode']);

            $specimen->setWellPlate($plate, $data['position']);
        }
    }

    private function getWellPlateAndPositions()
    {
        return [
            [
                'barcode' => self::PLATE_ONE_BARCODE,
                'position' => 54,
            ],
            [
                'barcode' => self::PLATE_ONE_BARCODE,
                'position' => 58,
            ],
            [
                'barcode' => self::PLATE_ONE_BARCODE,
                'position' => 62,
            ],
            [
                'barcode' => self::PLATE_ONE_BARCODE,
                'position' => 66,
            ],
        ];
    }

    private function getRandomStorableSpecimen(ObjectManager $em): Specimen
    {
        if (null === $this->storableSpecimens) {
            $this->storableSpecimens = $em->getRepository(Specimen::class)
                ->createQueryBuilder('s')
                ->where('s.status IN (:storableStatuses)')
                ->setParameter('storableStatuses', [
                    Specimen::STATUS_ACCEPTED,
                    Specimen::STATUS_RESULTS,
                ])
                ->getQuery()
                ->execute()
            ;
        }

        if (is_array($this->storableSpecimens) && count($this->storableSpecimens) === 0) {
            throw new \RuntimeException('Ran out of storable Specimen fixtures');
        }

        $key = array_rand($this->storableSpecimens);

        $specimen = $this->storableSpecimens[$key];
        unset($this->storableSpecimens[$key]);

        return $specimen;
    }
}

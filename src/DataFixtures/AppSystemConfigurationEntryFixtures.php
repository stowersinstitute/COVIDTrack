<?php

namespace App\DataFixtures;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Entity\SystemConfigurationEntry;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class AppSystemConfigurationEntryFixtures extends Fixture
{
    public function load(ObjectManager $em)
    {
        foreach ($this->getData() as $data) {
            $C = new SystemConfigurationEntry($data['referenceId']);
            $C->setValue($data['value']);

            $em->persist($C);
        }

        $em->flush();
    }

    private function getData()
    {
        return [
            [
                'referenceId' => SpecimenAccessionIdGenerator::BASE_KEY_CONFIG_ID,
                'value' => $this->makeRandomHexString(),
            ],
            [
                'referenceId' => SpecimenAccessionIdGenerator::IV_CONFIG_ID,
                'value' => $this->makeRandomHexString(),
            ],
            [
                'referenceId' => SpecimenAccessionIdGenerator::PASSWORD_CONFIG_ID,
                'value' => $this->makeRandomHexString(),
            ],
        ];
    }

    private function makeRandomHexString(int $length = 32): string
    {
        $string = '';
        for ($i=0; $i<$length; $i++) {
            $code = rand(65, 70); // A-F
            $string .= chr($code);
        }

        return $string;
    }
}

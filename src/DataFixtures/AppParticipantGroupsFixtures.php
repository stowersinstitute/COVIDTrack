<?php

namespace App\DataFixtures;

use App\Entity\ParticipantGroup;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class AppParticipantGroupsFixtures extends Fixture
{
    public function load(ObjectManager $em)
    {
        $groups = [];
        $numToCreate = 5;
        $participantCount = 5;
        for ($i=0; $i<$numToCreate; $i++) {
            $accessionId = 'GRP-'.($i+1);
            $g = new ParticipantGroup($accessionId, $participantCount++);
            $g->setTitle($this->getGroupTitle($i));

            $groups[] = $g;

            $em->persist($g);
        }

        $em->flush();
    }

    private function getGroupTitle(int $idx): string
    {
        $titles = [
            'Amber Alligators',
            'Brown Bears',
            'Cyan Chickens',
            'Denim Dingos',
            'Emerald Eels',
            'Fuchsia Fish',
            'Golden Geese',
            'Heliotrope Herons',
            'Indigo Impalas',
            'Jade Jellyfish',
            'Khaki Kangaroos',
            'Lavender Lemurs',
            'Mauve Meerkats',
            'Navy Nightingales',
            'Olive Otters',
            'Pink Pelicans',
            'Quartz Quails',
            'Ruby Raccoons',
            'Scarlet Sloths',
            'Teal Tigers',
            'Ultramarine Urchins',
            'Violet Vultures',
            'White Walruses',
            'Xanthic Xenons',
            'Yellow Yaks',
            'Zero Zebras',
        ];

        if (!isset($titles[$idx])) {
            throw new \InvalidArgumentException('No fixture ParticipantGroup title exists for index ' . $idx);
        }

        return $titles[$idx];
    }
}

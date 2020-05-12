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
        foreach ($this->getData() as $raw) {
            $accessionId = $raw['accessionId'];
            $g = new ParticipantGroup($accessionId, $raw['participantCount']);
            $g->setTitle($raw['title']);

            $groups[] = $g;

            $em->persist($g);
        }

        $em->flush();
    }

    private function getData(): array
    {
        return [
            [ 'title' => 'Red',         'participantCount' => 3,    'accessionId' => 'GRP-722XJW' ],
            [ 'title' => 'Orange',      'participantCount' => 5,    'accessionId' => 'GRP-ZRGTSS' ],
            [ 'title' => 'Yellow',      'participantCount' => 7,    'accessionId' => 'GRP-7PRMZC' ],
            [ 'title' => 'Green',       'participantCount' => 9,    'accessionId' => 'GRP-N9YNSH' ],
            [ 'title' => 'Blue',        'participantCount' => 11,   'accessionId' => 'GRP-9LT5SY' ],
            [ 'title' => 'Indigo',      'participantCount' => 13,   'accessionId' => 'GRP-WCKXJT' ],
            [ 'title' => 'Violet',      'participantCount' => 15,   'accessionId' => 'GRP-CRYGX9' ],
        ];
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

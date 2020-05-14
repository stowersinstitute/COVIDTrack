<?php

namespace App\DataFixtures;

use App\Entity\ParticipantGroup;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class AppParticipantGroupsFixtures extends Fixture
{
    public function load(ObjectManager $em)
    {
        foreach ($this->getData() as $raw) {
            $g = new ParticipantGroup($raw['accessionId'], $raw['participantCount']);
            $g->setTitle($raw['title']);

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
}

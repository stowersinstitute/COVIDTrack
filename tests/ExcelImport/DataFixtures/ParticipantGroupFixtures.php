<?php

namespace App\Tests\ExcelImport\DataFixtures;

use App\Entity\ParticipantGroup;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Creates Participant Groups for testing Excel Imports of other downstream
 * data that depends on Participant Groups.
 */
class ParticipantGroupFixtures extends Fixture
{
    public function load(ObjectManager $em)
    {
        foreach ($this->getData() as $data) {
            $group = new ParticipantGroup($data['accessionId'], $data['participantCount']);
            $group->setTitle($data['title']);
            $group->setExternalId($data['externalId']);

            $this->setReference($data['referenceId'], $group);

            $em->persist($group);
        }

        $em->flush();
    }

    public function getData(): array
    {
        return [
            [
                'referenceId' => 'tests.group.blue',
                'title' => 'Blue',
                'accessionId' => 'GRP-1',
                'participantCount' => 5,
                'externalId' => 'EXT-1',
            ],
            [
                'referenceId' => 'tests.group.red',
                'title' => 'Red',
                'accessionId' => 'GRP-2',
                'participantCount' => 6,
                'externalId' => 'EXT-2',
            ],
            [
                'referenceId' => 'tests.group.green',
                'title' => 'Green',
                'accessionId' => 'GRP-3',
                'participantCount' => 7,
                'externalId' => 'EXT-3',
            ],
        ];
    }
}

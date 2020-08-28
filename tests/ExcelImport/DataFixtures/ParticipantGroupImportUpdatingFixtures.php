<?php

namespace App\Tests\ExcelImport\DataFixtures;

use App\Entity\ParticipantGroup;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Creates Participant Groups for testing Excel Import of updating Participant Groups.
 */
class ParticipantGroupImportUpdatingFixtures extends Fixture
{
    public function load(ObjectManager $em)
    {
        foreach ($this->getData() as $data) {
            $group = new ParticipantGroup($data['accessionId'], $data['participantCount']);
            $group->setTitle($data['title']);
            $group->setExternalId($data['externalId']);
            $group->setIsActive($data['isActive']);

            $em->persist($group);
        }

        $em->flush();
    }

    /**
     * Text in "externalId" keys must match a line in
     * participant-group-importer-updating.xlsx
     */
    public function getData(): array
    {
        return [
            [
                'externalId' => 'SNUP1',
                'title' => 'Update Me1',
                'accessionId' => 'GRP-1',
                'participantCount' => 1,
                'isActive' => true,
            ],
            [
                'externalId' => 'SNUP2',
                'title' => 'Update Me2',
                'accessionId' => 'GRP-2',
                'participantCount' => 2,
                'isActive' => true,
            ],
            [
                'externalId' => 'SNUP3',
                'title' => 'Update Me3',
                'accessionId' => 'GRP-3',
                'participantCount' => 3,
                'isActive' => true,
            ],
            [
                'externalId' => 'SNUP4',
                'title' => 'Update Me4',
                'accessionId' => 'GRP-4',
                'participantCount' => 4,
                'isActive' => true,
            ],
            [
                'externalId' => 'AlwaysActiveGroup',
                'title' => 'Should Always Be Active',
                'accessionId' => 'GRP-5',
                'participantCount' => 5,
                'isActive' => true,
            ],
            [
                'externalId' => 'AlwaysInactiveGroup',
                'title' => 'Should Always Be Inactive',
                'accessionId' => 'GRP-6',
                'participantCount' => 6,
                'isActive' => false,
            ],
            [
                'externalId' => 'ToggleToActiveGroup',
                'title' => 'Made Active By Update',
                'accessionId' => 'GRP-7',
                'participantCount' => 7,
                'isActive' => false, // Update import will change to TRUE
            ],
            [
                'externalId' => 'ToggleToInactiveGroup',
                'title' => 'Made Inactive By Update',
                'accessionId' => 'GRP-8',
                'participantCount' => 8,
                'isActive' => true, // Update import will change to FALSE
            ],
        ];
    }
}

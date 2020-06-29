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
            ],
            [
                'externalId' => 'SNUP2',
                'title' => 'Update Me2',
                'accessionId' => 'GRP-2',
                'participantCount' => 2,
            ],
            [
                'externalId' => 'SNUP3',
                'title' => 'Update Me3',
                'accessionId' => 'GRP-3',
                'participantCount' => 3,
            ],
            [
                'externalId' => 'SNUP4',
                'title' => 'Update Me4',
                'accessionId' => 'GRP-4',
                'participantCount' => 4,
            ],
        ];
    }
}

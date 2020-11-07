<?php

namespace App\DataFixtures;

use App\Entity\ParticipantGroup;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class AppParticipantGroupsFixtures extends Fixture
{
    public function load(ObjectManager $em)
    {
        /** @var ParticipantGroup[] $groupsToSchedule */
        $groupsToSchedule = [];
        foreach ($this->getData() as $raw) {
            $g = new ParticipantGroup($raw['accessionId'], $raw['participantCount']);
            $g->setTitle($raw['title']);

            if (isset($raw['externalId'])) {
                $g->setExternalId($raw['externalId']);
            }

            if (isset($raw['isControl'])) {
                $g->setIsControl($raw['isControl']);
            }

            if (isset($raw['isActive'])) {
                $g->setIsActive($raw['isActive']);
            }

            $g->setAcceptsSalivaSpecimens($raw['acceptsSalivaSpecimens']);
            $g->setAcceptsBloodSpecimens($raw['acceptsBloodSpecimens']);
            $g->setViralResultsWebHooksEnabled($raw['viralResultsWebHooksEnabled']);
            $g->setAntibodyResultsWebHooksEnabled($raw['antibodyResultsWebHooksEnabled']);

            // group.Red
            $referenceId = 'group.' . $g->getTitle();
            $this->addReference($referenceId, $g);

            $em->persist($g);

            if ($g->isActive()) {
                $groupsToSchedule[] = $g;
            }
        }

        $em->flush();
    }

    private function getData(): array
    {
        return [
            [
                'title' => 'Red',
                'participantCount' => 3,
                'accessionId' => 'GRP-722XJW',
                'externalId' => 'EXT-RED',
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
                'viralResultsWebHooksEnabled' => false,
                'antibodyResultsWebHooksEnabled' => false,
            ],
            [
                'title' => 'Orange',
                'participantCount' => 5,
                'accessionId' => 'GRP-ZRGTSS',
                'externalId' => 'EXT-ORANGE',
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
                'viralResultsWebHooksEnabled' => false,
                'antibodyResultsWebHooksEnabled' => false,
            ],
            [
                'title' => 'Yellow',
                'participantCount' => 7,
                'accessionId' => 'GRP-7PRMZC',
                'externalId' => 'EXT-YELLOW',
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
                'viralResultsWebHooksEnabled' => false,
                'antibodyResultsWebHooksEnabled' => false,
            ],
            [
                'title' => 'Green',
                'participantCount' => 9,
                'accessionId' => 'GRP-N9YNSH',
                'externalId' => 'EXT-GREEN',
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
                'viralResultsWebHooksEnabled' => false,
                'antibodyResultsWebHooksEnabled' => false,
            ],
            [
                'title' => 'Blue',
                'participantCount' => 11,
                'accessionId' => 'GRP-9LT5SY',
                'externalId' => 'EXT-BLUE',
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
                'viralResultsWebHooksEnabled' => false,
                'antibodyResultsWebHooksEnabled' => false,
            ],
            [
                'title' => 'Indigo',
                'participantCount' => 13,
                'accessionId' => 'GRP-WCKXJT',
                'externalId' => 'EXT-INDIGO',
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
                'viralResultsWebHooksEnabled' => false,
                'antibodyResultsWebHooksEnabled' => false,
            ],
            [
                'title' => 'Violet',
                'participantCount' => 15,
                'accessionId' => 'GRP-CRYGX9',
                'externalId' => 'EXT-VIOLET',
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
                'viralResultsWebHooksEnabled' => false,
                'antibodyResultsWebHooksEnabled' => false,
            ],
            [
                'title' => 'CONTROL',
                'participantCount' => 0,
                'accessionId' => 'GRP-CTRLLL',
                'externalId' => 'EXT-CONTROL',
                'isControl' => true,
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
                'viralResultsWebHooksEnabled' => false,
                'antibodyResultsWebHooksEnabled' => false,
            ],
            [
                'title' => 'Inactive Research',
                'participantCount' => 2,
                'accessionId' => 'GRP-INAC-RES',
                'externalId' => 'EXT-INAC-RES',
                'isActive' => false,
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
                'viralResultsWebHooksEnabled' => false,
                'antibodyResultsWebHooksEnabled' => false,
            ],
            [
                'title' => 'Inactive Individual',
                'participantCount' => 1,
                'accessionId' => 'GRP-INAC-IND',
                'externalId' => 'EXT-INAC-IND',
                'isActive' => false,
                'viralResultsWebHooksEnabled' => true,
                'antibodyResultsWebHooksEnabled' => true,
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
            ],
            [
                'title' => 'Individual 1',
                'participantCount' => 1,
                'accessionId' => 'GRP-INDV1',
                'externalId' => '936c5d7d1b16189009a0dd3bdc4bcbae', // Real ID from stowersdev.service-now.com, but has "Report" flags === false
                'isControl' => false,
                'viralResultsWebHooksEnabled' => true,
                'antibodyResultsWebHooksEnabled' => true,
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
            ],
            [
                'title' => 'Individual No Web Hooks',
                'participantCount' => 1,
                'accessionId' => 'GRP-INDV2',
                'externalId' => '1c7cdd7d1b16189009a0dd3bdc4bcb23', // Real ID from stowersdev.service-now.com
                'isControl' => false,
                'viralResultsWebHooksEnabled' => false,
                'antibodyResultsWebHooksEnabled' => false,
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
            ],
            [
                'title' => 'Individual No Specimens Allowed',
                'participantCount' => 1,
                'accessionId' => 'GRP-IND-NO-SPEC',
                'externalId' => '9bd1fa8a1b52989009a0dd3bdc4bcb26', // Real ID from stowersdev.service-now.com
                'isControl' => false,
                'viralResultsWebHooksEnabled' => false,
                'antibodyResultsWebHooksEnabled' => false,
                'acceptsBloodSpecimens' => false,
                'acceptsSalivaSpecimens' => false,
            ],
        ];
    }
}

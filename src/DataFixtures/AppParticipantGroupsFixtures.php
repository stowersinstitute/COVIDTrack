<?php

namespace App\DataFixtures;

use App\Entity\ParticipantGroup;
use App\Scheduling\ParticipantGroupRoundRobinScheduler;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

class AppParticipantGroupsFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies()
    {
        return [
            AppDropOffScheduleFixtures::class,
        ];
    }

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

        // Schedule groups into available drop off windows
        $scheduler = new ParticipantGroupRoundRobinScheduler();
        $scheduler->assignByDays($groupsToSchedule, $this->getReference('SiteDropOffSchedule.default'));

        $em->flush();
    }

    private function getData(): array
    {
        return [
            [
                'title' => 'Red',
                'participantCount' => 3,
                'accessionId' => 'GRP-722XJW',
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
                'viralResultsWebHooksEnabled' => false,
                'antibodyResultsWebHooksEnabled' => false,
            ],
            [
                'title' => 'Orange',
                'participantCount' => 5,
                'accessionId' => 'GRP-ZRGTSS',
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
                'viralResultsWebHooksEnabled' => false,
                'antibodyResultsWebHooksEnabled' => false,
            ],
            [
                'title' => 'Yellow',
                'participantCount' => 7,
                'accessionId' => 'GRP-7PRMZC',
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
                'viralResultsWebHooksEnabled' => false,
                'antibodyResultsWebHooksEnabled' => false,
            ],
            [
                'title' => 'Green',
                'participantCount' => 9,
                'accessionId' => 'GRP-N9YNSH',
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
                'viralResultsWebHooksEnabled' => false,
                'antibodyResultsWebHooksEnabled' => false,
            ],
            [
                'title' => 'Blue',
                'participantCount' => 11,
                'accessionId' => 'GRP-9LT5SY',
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
                'viralResultsWebHooksEnabled' => false,
                'antibodyResultsWebHooksEnabled' => false,
            ],
            [
                'title' => 'Indigo',
                'participantCount' => 13,
                'accessionId' => 'GRP-WCKXJT',
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
                'viralResultsWebHooksEnabled' => false,
                'antibodyResultsWebHooksEnabled' => false,
            ],
            [
                'title' => 'Violet',
                'participantCount' => 15,
                'accessionId' => 'GRP-CRYGX9',
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
                'viralResultsWebHooksEnabled' => false,
                'antibodyResultsWebHooksEnabled' => false,
            ],
            [
                'title' => 'CONTROL',
                'participantCount' => 0,
                'accessionId' => 'GRP-CTRLLL',
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
                'externalId' => 'abcdefghijklmnopqrstuvwxyz654321',
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
                'externalId' => 'abcdefghijklmnopqrstuvwxyz654323',
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
                'externalId' => 'abcdefghijklmnopqrstuvwxyz654324',
                'isControl' => false,
                'viralResultsWebHooksEnabled' => false,
                'antibodyResultsWebHooksEnabled' => false,
                'acceptsBloodSpecimens' => false,
                'acceptsSalivaSpecimens' => false,
            ],
        ];
    }
}

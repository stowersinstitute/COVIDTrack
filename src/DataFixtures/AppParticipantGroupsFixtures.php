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

            $g->setEnabledForResultsWebHooks($raw['enabledForResultsWebHooks']);
            $g->setAcceptsSalivaSpecimens($raw['acceptsSalivaSpecimens']);
            $g->setAcceptsBloodSpecimens($raw['acceptsBloodSpecimens']);

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
                'enabledForResultsWebHooks' => false,
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
            ],
            [
                'title' => 'Orange',
                'participantCount' => 5,
                'accessionId' => 'GRP-ZRGTSS',
                'enabledForResultsWebHooks' => false,
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
            ],
            [
                'title' => 'Yellow',
                'participantCount' => 7,
                'accessionId' => 'GRP-7PRMZC',
                'enabledForResultsWebHooks' => false,
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
            ],
            [
                'title' => 'Green',
                'participantCount' => 9,
                'accessionId' => 'GRP-N9YNSH',
                'enabledForResultsWebHooks' => false,
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
            ],
            [
                'title' => 'Blue',
                'participantCount' => 11,
                'accessionId' => 'GRP-9LT5SY',
                'enabledForResultsWebHooks' => false,
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
            ],
            [
                'title' => 'Indigo',
                'participantCount' => 13,
                'accessionId' => 'GRP-WCKXJT',
                'enabledForResultsWebHooks' => false,
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
            ],
            [
                'title' => 'Violet',
                'participantCount' => 15,
                'accessionId' => 'GRP-CRYGX9',
                'enabledForResultsWebHooks' => false,
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
            ],
            [
                'title' => 'CONTROL',
                'participantCount' => 0,
                'accessionId' => 'GRP-CTRLLL',
                'isControl' => true,
                'enabledForResultsWebHooks' => false,
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
            ],
            [
                'title' => 'Inactive Research',
                'participantCount' => 2,
                'accessionId' => 'GRP-INAC-RES',
                'isActive' => false,
                'enabledForResultsWebHooks' => false,
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
            ],
            [
                'title' => 'Inactive Individual',
                'participantCount' => 1,
                'accessionId' => 'GRP-INAC-IND',
                'isActive' => false,
                'enabledForResultsWebHooks' => true,
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
            ],
            [
                'title' => 'Individual 1',
                'participantCount' => 1,
                'accessionId' => 'GRP-INDV1',
                'externalId' => 'abcdefghijklmnopqrstuvwxyz654321',
                'isControl' => false,
                'enabledForResultsWebHooks' => true,
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
            ],
            [
                'title' => 'Individual No Web Hooks',
                'participantCount' => 1,
                'accessionId' => 'GRP-INDV2',
                'externalId' => 'abcdefghijklmnopqrstuvwxyz654323',
                'isControl' => false,
                'enabledForResultsWebHooks' => false,
                'acceptsBloodSpecimens' => true,
                'acceptsSalivaSpecimens' => true,
            ],
            [
                'title' => 'Individual No Specimens Allowed',
                'participantCount' => 1,
                'accessionId' => 'GRP-IND-NO-SPEC',
                'externalId' => 'abcdefghijklmnopqrstuvwxyz654324',
                'isControl' => false,
                'enabledForResultsWebHooks' => false,
                'acceptsBloodSpecimens' => false,
                'acceptsSalivaSpecimens' => false,
            ],
        ];
    }
}

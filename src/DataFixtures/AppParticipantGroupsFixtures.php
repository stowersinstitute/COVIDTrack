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
        /** @var ParticipantGroup[] $groups */
        $groups = [];
        foreach ($this->getData() as $raw) {
            $g = new ParticipantGroup($raw['accessionId'], $raw['participantCount']);
            $g->setTitle($raw['title']);

            $em->persist($g);
            $groups[] = $g;
        }

        $em->flush();

        // Schedule groups into available drop off windows
        $scheduler = new ParticipantGroupRoundRobinScheduler();
        $scheduler->assignByDays($groups, $this->getReference('SiteDropOffSchedule.default'));

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

<?php

namespace App\DataFixtures;

use App\Entity\CollectionEvent;
use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $em)
    {
        $users = $this->addUsers($em);
        $groups = $this->addParticipantGroups($em);
        $events = $this->addCollectionEvents($em);
        $specimens = $this->addPrintedSpecimens($em, $groups, $events);

        $em->flush();
    }

    private function addUsers(ObjectManager $em): array
    {
        return [];
    }

    /**
     * @return ParticipantGroup[]
     */
    private function addParticipantGroups(ObjectManager $em): array
    {
        $groups = [];
        $numToCreate = 10;
        for ($i=1; $i<=$numToCreate; $i++) {
            $accessionId = 'GRP-'.$i;
            $g = new ParticipantGroup($accessionId);

            $groups[] = $g;

            $em->persist($g);
        }

        return $groups;
    }

    private function addCollectionEvents(ObjectManager $em)
    {
        $events = [];
        $numToCreate = 5;
        for ($i=1; $i<=$numToCreate; $i++) {
            $e = new CollectionEvent();

            $e->setTitle('Event '.$i);

            $collectedOn = new \DateTime(sprintf('-%d days 11:00am', $numToCreate-$i));
            $e->setCollectedOn($collectedOn);

            $events[] = $e;

            $em->persist($e);
        }

        return $events;
    }

    /**
     * Add Specimens that have had labels printed, but not imported with results.
     *
     * @param ObjectManager $em
     * @param ParticipantGroup[] $groups
     * @param CollectionEvent[] $events
     */
    private function addPrintedSpecimens(ObjectManager $em, array $groups, array $events)
    {
        // TODO: CVDLS-30 Support creating unique accession ID when creating
        // Invoke to get next Specimen accession id
        $nextSpecimenId = function() {
            if (!isset($seq)) {
                static $seq = 0;
            }
            $prefix = 'CID';

            $seq++;

            return sprintf("%s%s", $prefix, $seq);
        };

        $numPerGroupPerEvent = 10;
        foreach ($events as $event) {
            foreach ($groups as $group) {
                for ($i=1; $i<=$numPerGroupPerEvent; $i++) {
                    $s = new Specimen($nextSpecimenId(), $group, $event);

                    $em->persist($s);
                }
            }
        }
    }
}

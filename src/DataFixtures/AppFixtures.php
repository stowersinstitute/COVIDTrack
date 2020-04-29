<?php

namespace App\DataFixtures;

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
        $specimens = $this->addPrintedSpecimens($em, $groups);

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
        $participantCount = 5;
        for ($i=1; $i<=$numToCreate; $i++) {
            $accessionId = 'GRP-'.$i;
            $g = new ParticipantGroup($accessionId, $participantCount++);

            $groups[] = $g;

            $em->persist($g);
        }

        return $groups;
    }

    /**
     * Add Specimens that have had labels printed, but not imported with results.
     *
     * @param ObjectManager $em
     * @param ParticipantGroup[] $groups
     */
    private function addPrintedSpecimens(ObjectManager $em, array $groups)
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
    }
}

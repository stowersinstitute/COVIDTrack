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
        for ($i=0; $i<$numToCreate; $i++) {
            $accessionId = 'GRP-'.($i+1);
            $g = new ParticipantGroup($accessionId, $participantCount++);
            $g->setTitle($this->getGroupTitle($i));

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

        foreach ($groups as $group) {
            for ($i=1; $i<=$group->getParticipantCount(); $i++) {
                $s = new Specimen($nextSpecimenId(), $group);
                $s->setType($this->getSpecimenType($i));

                $em->persist($s);
            }
        }
    }

    private function getSpecimenType(int $i)
    {
        $types = array_values(Specimen::getFormTypes());

        return $types[$i % count($types)];
    }

    private function getGroupTitle(int $idx): string
    {
        $titles = [
            'Amber Alligators',
            'Brown Bears',
            'Cyan Chickens',
            'Denim Dingos',
            'Emerald Eels',
            'Fuchsia Fish',
            'Golden Geese',
            'Heliotrope Herons',
            'Indigo Impalas',
            'Jade Jellyfish',
            'Khaki Kangaroos',
            'Lavender Lemurs',
            'Mauve Meerkats',
            'Navy Nightingales',
            'Olive Otters',
            'Pink Pelicans',
            'Quartz Quails',
            'Ruby Raccoons',
            'Scarlet Sloths',
            'Teal Tigers',
            'Ultramarine Urchins',
            'Violet Vultures',
            'White Walruses',
            'Xanthic Xenons',
            'Yellow Yaks',
            'Zero Zebras',
        ];

        if (!isset($titles[$idx])) {
            throw new \InvalidArgumentException('No fixture ParticipantGroup title exists for index ' . $idx);
        }

        return $titles[$idx];
    }
}

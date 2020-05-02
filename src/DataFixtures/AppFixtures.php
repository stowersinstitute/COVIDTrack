<?php

namespace App\DataFixtures;

use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\SpecimenResult;
use App\Entity\SpecimenResultDDPCR;
use App\Entity\SpecimenResultQPCR;
use App\Entity\SpecimenResultSequencing;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $em)
    {
        $users = $this->addUsers($em);
        $groups = $this->addParticipantGroups($em);
        $this->addPrintedSpecimens($em, $groups);
        $this->addResultedSpecimens($em, $groups);

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
        foreach ($groups as $group) {
            for ($i=1; $i<=$group->getParticipantCount(); $i++) {
                $s = new Specimen($this->getNextSpecimenId(), $group);

                $em->persist($s);
            }
        }
    }

    /**
     * Add Specimens that have had labels printed and results.
     *
     * @param ObjectManager $em
     * @param ParticipantGroup[] $groups
     */
    private function addResultedSpecimens(ObjectManager $em, array $groups)
    {
        foreach ($groups as $group) {
            for ($i=1; $i<=$group->getParticipantCount(); $i++) {
                $s = new Specimen($this->getNextSpecimenId(), $group);
                $em->persist($s);

                // Add many qPCR results, which test for presence of virus
                $maxQPCR = rand(2,4);
                for ($j=0; $j<$maxQPCR; $j++) {
                    $r1 = new SpecimenResultQPCR($s);

                    // This sadly isn't working. See Gedmo\AbstractTrackingListener#prePersist()
                    $r1->setCreatedAt(new \DateTime(sprintf('-%d days', $i)));

                    // Set a random conclusion
                    $conclusions = SpecimenResultQPCR::getFormConclusions();
                    $r1->setConclusion($conclusions[array_rand($conclusions)]);

                    // Set failure if conclusion was invalid
                    if ($r1->getConclusion() === SpecimenResultQPCR::CONCLUSION_INVALID) {
                        $r1->setIsFailure(rand(0,1));
                    }

                    $em->persist($r1);
                }

                // ddPCR Result
                $r2 = new SpecimenResultDDPCR($s);
                $r2->setIsFailure(rand(0,1));
                $s->addResult($r2);
                $em->persist($r2);

                // Sequencing Result
                $r3 = new SpecimenResultSequencing($s);
                $r3->setIsFailure(rand(0,1));
                $s->addResult($r3);
                $em->persist($r3);
            }
        }
    }

    /**
     * Invoke to get next Specimen accessionId
     * TODO: CVDLS-30 Support creating unique accession ID when creating
     *
     * @return string
     */
    private function getNextSpecimenId(): string
    {
        if (!isset($seq)) {
            static $seq = 0;
        }
        $prefix = 'CID';

        $seq++;

        return sprintf("%s%s", $prefix, $seq);
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

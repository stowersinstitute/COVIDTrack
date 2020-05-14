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
        $fixtureData = [
            [ 'title' => 'Red',         'participantCount' => 3,    'accessionId' => 'GRP-722XJW' ],
            [ 'title' => 'Orange',      'participantCount' => 5,    'accessionId' => 'GRP-ZRGTSS' ],
            [ 'title' => 'Yellow',      'participantCount' => 7,    'accessionId' => 'GRP-7PRMZC' ],
            [ 'title' => 'Green',       'participantCount' => 9,    'accessionId' => 'GRP-N9YNSH' ],
            [ 'title' => 'Blue',        'participantCount' => 11,   'accessionId' => 'GRP-9LT5SY' ],
            [ 'title' => 'Indigo',      'participantCount' => 13,   'accessionId' => 'GRP-WCKXJT' ],
            [ 'title' => 'Violet',      'participantCount' => 15,   'accessionId' => 'GRP-CRYGX9' ],
        ];

        $groups = [];
        foreach ($fixtureData as $raw) {
            $accessionId = $raw['accessionId'];
            $g = new ParticipantGroup($accessionId, $raw['participantCount']);
            $g->setTitle($raw['title']);

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
            // Generate Resulted Specimens for all Group Participants
            // for this many days worth of testing
            $daysWorthResults = 3;

            for ($day=1; $day<=$daysWorthResults; $day++) {
                for ($i=1; $i<=$group->getParticipantCount(); $i++) {
                    $s = new Specimen($this->getNextSpecimenId(), $group);
                    $s->setType($this->getSpecimenType($i));
                    $s->setCollectedAt(new \DateTimeImmutable(sprintf('-%d days 5:00pm', $day)));
                    $s->setStatus(Specimen::STATUS_RESULTS);

                    $em->persist($s);

                    // Add many qPCR results, which test for presence of virus
                    $maxQPCR = rand(2,4);
                    for ($j=0; $j<$maxQPCR; $j++) {
                        $r1 = new SpecimenResultQPCR($s);

                        // This sadly isn't working. See Gedmo\AbstractTrackingListener#prePersist()
                        $r1->setCreatedAt(new \DateTimeImmutable(sprintf('-%d days', $i)));

                        // Set a random conclusion
                        $conclusions = SpecimenResultQPCR::getFormConclusions();
                        $r1->setConclusion($conclusions[array_rand($conclusions)]);

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
    }

    private function getSpecimenType(int $i)
    {
        $types = array_values(Specimen::getFormTypes());

        return $types[$i % count($types)];
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

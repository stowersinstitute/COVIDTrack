<?php

namespace App\DataFixtures;

use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\SpecimenResultDDPCR;
use App\Entity\SpecimenResultQPCR;
use App\Entity\SpecimenResultSequencing;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

class AppFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies()
    {
        return [
            AppParticipantGroupsFixtures::class,
        ];
    }

    public function load(ObjectManager $em)
    {
        /** @var ParticipantGroup[] $groups */
        $groups = $em->getRepository(ParticipantGroup::class)->findAll();

        $this->addPrintedSpecimens($em, $groups);
        $this->addResultedSpecimens($em, $groups);

        $em->flush();
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
        $possibleResults = $this->buildQPCRResultsDistribution();

        foreach ($groups as $group) {
            // Generate Resulted Specimens for all Group Participants
            // for this many days worth of testing
            $daysWorthResults = 3;

            for ($day=1; $day<=$daysWorthResults; $day++) {
                for ($i=1; $i<=$group->getParticipantCount(); $i++) {
                    $s = new Specimen($this->getNextSpecimenId(), $group);
                    $s->setType($this->getSpecimenType($i));

                    $em->persist($s);

                    // Add many qPCR results, which test for presence of virus
                    $maxQPCR = rand(1,2);
                    for ($j=0; $j<$maxQPCR; $j++) {
                        // Set a random conclusion, if we have one
                        $conclusion = $possibleResults[array_rand($possibleResults)];
                        if ($conclusion) {
                            $r1 = new SpecimenResultQPCR($s);
                            $r1->setCreatedAt(new \DateTime(sprintf('-%d days', $i)));
                            $r1->setConclusion($conclusion);

                            $s->setStatus(Specimen::STATUS_RESULTS);
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

    /**
     * Build array of possible qPCR Results across a probability distribution.
     * Pull a random element from this array to get a random result.
     *
     * Returns NULL when no result available, such as when Awaiting Results.
     */
    private function buildQPCRResultsDistribution(): array
    {
        // Approximate hit rate out of 100
        $positive = 6;
        $recommended = 4;
        $negative = 72;
        $inconclusive = 10;
        $awaitingResults = 8;

        $possible = array_merge(
            array_fill(0, $positive, SpecimenResultQPCR::CONCLUSION_POSITIVE),
            array_fill(0, $recommended, SpecimenResultQPCR::CONCLUSION_RECOMMENDED),
            array_fill(0, $negative, SpecimenResultQPCR::CONCLUSION_NEGATIVE),
            array_fill(0, $inconclusive, SpecimenResultQPCR::CONCLUSION_INCONCLUSIVE),
            array_fill(0, $awaitingResults, null)
        );

        return $possible;
    }
}

<?php

namespace App\DataFixtures;

use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\SpecimenResultQPCR;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

class AppFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * Stores Specimen.id loaded with Results during this fixture class
     * @var int[]
     */
    private $specimenIdsWithResults = [];

    public function getDependencies()
    {
        return [
            AppParticipantGroupsFixtures::class,
            AppTubeFixtures::class,
        ];
    }

    public function load(ObjectManager $em)
    {
        $this->addResultedSpecimens($em);

        $em->flush();
    }

    /**
     * Add Specimens that have had labels printed and results.
     *
     * @param ObjectManager $em
     */
    private function addResultedSpecimens(ObjectManager $em)
    {
        /** @var ParticipantGroup[] $groups */
        $groups = $em->getRepository(ParticipantGroup::class)->findAll();

        // Reasonable positive/negative rate
        $possibleResults = $this->buildQPCRResultsDistribution();

        foreach ($groups as $group) {
            // Generate Resulted Specimens for all Group Participants
            // for this many days worth of testing
            $daysWorthResults = 3;

            for ($day=1; $day<=$daysWorthResults; $day++) {
                for ($i=1; $i<=$group->getParticipantCount(); $i++) {
                    $s = $this->getRandomSpecimenPendingResultsForGroup($em, $group);

                    // Might not have enough fixture Tubes to keep going
                    if (!$s) continue;

                    // Set a random conclusion, if we have one
                    $conclusion = $possibleResults[array_rand($possibleResults)];
                    if ($conclusion) {
                        $r1 = new SpecimenResultQPCR($s);
                        $r1->setCreatedAt(new \DateTime(sprintf('-%d days', $day)));
                        $r1->setConclusion($conclusion);

                        $s->setStatus(Specimen::STATUS_RESULTS);

                        $em->persist($r1);
                    }
                }
            }
        }
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

    private function getRandomSpecimenPendingResultsForGroup(ObjectManager $em, ParticipantGroup $group): ?Specimen
    {
        /** @var Specimen[] $specimens */
        $qb = $em->getRepository(Specimen::class)
            ->createQueryBuilder('s')

            // Group
            ->andWhere('s.participantGroup = :group')
            ->setParameter('group', $group)

            // Doesn't have a CLIA testing rec yet
            ->andWhere('s.cliaTestingRecommendation = :recommendation')
            ->setParameter('recommendation', Specimen::CLIA_REC_PENDING)

            ->setMaxResults(1);

        // Not a Specimen we already added results for,
        // so we don't have to flush() after each loop
        if ($this->specimenIdsWithResults) {
            $qb->andWhere('s.id NOT IN (:seenSpecimenIds)')
                ->setParameter('seenSpecimenIds', $this->specimenIdsWithResults);
        }

        $specimens = $qb->getQuery()->execute();

        if (count($specimens) !== 1) {
            // Might've run out of Specimens to result
            return null;
        }

        $found = array_shift($specimens);
        $this->specimenIdsWithResults[] = $found->getId();

        return $found;
    }
}

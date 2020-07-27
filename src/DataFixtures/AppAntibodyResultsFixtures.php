<?php

namespace App\DataFixtures;

use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\SpecimenResultAntibody;
use App\Entity\SpecimenWell;
use App\Entity\WellPlate;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Import test results of Blood Antibody testing.
 */
class AppAntibodyResultsFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * Stores Specimen.id loaded with Results during this fixture class
     * @var int[]
     */
    private $specimenIdsWithResults = [];

    /**
     * Tracks which Well Plates have been created in this fixture class.
     *
     * @var WellPlate[] Keys are WellPlate.barcode, Values are WellPlate entities
     */
    private $createdPlatesByBarcode = [];

    public function getDependencies()
    {
        return [
            AppParticipantGroupsFixtures::class,
            AppBloodTubeFixtures::class,
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
        $possibleSignal = $this->buildSignalDistribution();

        // Add 1 result to many wells
        foreach ($groups as $group) {
            // Generate Resulted Specimens for all Group Participants
            // for this many days worth of testing
            $daysWorthResults = 3;

            for ($day=1; $day<=$daysWorthResults; $day++) {
                for ($i=1; $i<=$group->getParticipantCount(); $i++) {
                    $specimen = $this->getRandomSpecimenPendingResultsForGroup($em, $group);

                    // Might not have enough fixture Tubes to keep going
                    if (!$specimen) continue;

                    // Set a random conclusion, if we have one
                    $signal = $possibleSignal[array_rand($possibleSignal)];
                    if ($signal !== null) {
                        $resultDate = new \DateTimeImmutable(sprintf('-%d days', $day));

                        $well = $this->getSpecimenWellForFirstResult($specimen);

                        $conclusion = SpecimenResultAntibody::CONCLUSION_NEGATIVE;
                        if ($signal === SpecimenResultAntibody::SIGNAL_STRONG_NUMBER) {
                            $conclusion = SpecimenResultAntibody::CONCLUSION_POSITIVE;
                        }

                        // Add Result to Well
                        $result = new SpecimenResultAntibody($well, $conclusion, $signal);
                        $result->setCreatedAt($resultDate);

                        $em->persist($result);
                    }
                }
            }
        }

        // Must flush so below code knows about results we just created
        $em->flush();

        // Add a second Antibody Result to a few wells
        $multipleResults = [];
        foreach ($groups as $group) {
            for ($i=1; $i<=($group->getParticipantCount()/2); $i++) {
                $specimen = $this->getRandomSpecimenWithExistingAntibodyResultInGroup($em, $group);

                // This group might not have had any antibody results created above
                if (!$specimen) continue;

                // Set a random conclusion
                $signal = null;
                do {
                    $signal = $possibleSignal[array_rand($possibleSignal)];
                } while ($signal === null);
                $conclusion = SpecimenResultAntibody::CONCLUSION_NEGATIVE;
                if ($signal === SpecimenResultAntibody::SIGNAL_STRONG_NUMBER) {
                    $conclusion = SpecimenResultAntibody::CONCLUSION_POSITIVE;
                }

                $resultDate = new \DateTimeImmutable('-1 days');

                $results = $specimen->getAntibodyResults(1);
                $well = array_pop($results)->getWell();

                // Add another Result to this Well
                $result = new SpecimenResultAntibody($well, $conclusion, $signal);
                $result->setCreatedAt($resultDate);

                $multipleResults[] = $result;

                $em->persist($result);
            }
        }
        if (empty($multipleResults)) {
            throw new \RuntimeException('Could not load multiple Antibody Results');
        }
    }

    /**
     * Build array of possible Signal Results across a probability distribution.
     * Pull a random element from this array to get a random result.
     *
     * Returns NULL when no result available, such as when Awaiting Results.
     */
    private function buildSignalDistribution(): array
    {
        // Approximate hit rate out of 100
        $strong = 18;
        $weak = 4;
        $partial = 10;
        $negative = 60;
        $awaitingResults = 8;

        $possible = array_merge(
            array_fill(0, $strong, SpecimenResultAntibody::SIGNAL_STRONG_NUMBER),
            array_fill(0, $weak, SpecimenResultAntibody::SIGNAL_WEAK_NUMBER),
            array_fill(0, $partial, SpecimenResultAntibody::SIGNAL_PARTIAL_NUMBER),
            array_fill(0, $negative, SpecimenResultAntibody::SIGNAL_NEGATIVE_NUMBER),
            array_fill(0, $awaitingResults, null)
        );

        return $possible;
    }

    private function getRandomSpecimenPendingResultsForGroup(ObjectManager $em, ParticipantGroup $group): ?Specimen
    {
        /** @var Specimen[] $specimens */
        $qb = $em->getRepository(Specimen::class)
            ->createQueryBuilder('s')
            ->join('s.wells', 'wells')

            // Blood Specimen
            ->andWhere('s.type = :type')
            ->setParameter('type', Specimen::TYPE_BLOOD)

            // Group
            ->andWhere('s.participantGroup = :group')
            ->setParameter('group', $group)

            // Has been accepted by a Check-in Technician
            ->andWhere('s.status = :status')
            ->setParameter('status', Specimen::STATUS_ACCEPTED)

            // Is on a Well Plate
            ->andWhere('wells.wellPlate IS NOT NULL')

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

    private function getRandomSpecimenWithExistingAntibodyResultInGroup(ObjectManager $em, ParticipantGroup $group): ?Specimen
    {
        /** @var Specimen[] $specimens */
        $qb = $em->getRepository(Specimen::class)
            ->createQueryBuilder('s')
            ->join('s.wells', 'wells')

            // Saliva Specimen
            ->andWhere('s.type = :type')
            ->setParameter('type', Specimen::TYPE_BLOOD)

            // Group
            ->andWhere('s.participantGroup = :group')
            ->setParameter('group', $group)

            // Is on a Well Plate
            ->andWhere('wells.wellPlate IS NOT NULL')

            // Has an Antibody Result
            ->join('wells.resultsAntibody', 'antibodyResult')
            ->andWhere('antibodyResult IS NOT NULL')

            ->setMaxResults(1);

        $specimens = $qb->getQuery()->execute();

        // Might not have Antibody Result for this Group
        if (empty($specimens)) {
            return null;
        }

        $found = array_shift($specimens);

        $this->specimenIdsWithResults[] = $found->getId();

        return $found;
    }

    private function getSpecimenWellForFirstResult(Specimen $specimen): SpecimenWell
    {
        $wells = $specimen->getWells();
        if (count($wells) < 1) {
            throw new \RuntimeException(sprintf('Specimen %s is not yet on a Well Plate', $specimen->getAccessionId()));
        }

        return array_shift($wells);
    }
}

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

    /**
     * Tracks which Well Positions have been issued for each fixture Plate.
     *
     * @var array Keys are WellPlate.barcode, Values are last issued Well Position
     */
    private $platePositions = [];

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
                        if ($signal === SpecimenResultAntibody::CONCLUSION_QUANT_STRONG_NUMBER) {
                            $conclusion = SpecimenResultAntibody::CONCLUSION_POSITIVE;
                        }

                        // Add Result to Well
                        $result = new SpecimenResultAntibody($well, $conclusion, $signal);
                        $result->setCreatedAt($resultDate);

                        // Set Position normally coming from reporting result
                        $position = $this->getNextPositionForPlate($well->getWellPlate());
                        $well->setPositionAlphanumeric($position);

                        $em->persist($result);
                    }
                }
            }
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
        $strong = 6;
        $weak = 4;
        $partial = 10;
        $negative = 72;
        $awaitingResults = 8;

        $possible = array_merge(
            array_fill(0, $strong, SpecimenResultAntibody::CONCLUSION_QUANT_STRONG_NUMBER),
            array_fill(0, $weak, SpecimenResultAntibody::CONCLUSION_QUANT_WEAK_NUMBER),
            array_fill(0, $partial, SpecimenResultAntibody::CONCLUSION_QUANT_PARTIAL_NUMBER),
            array_fill(0, $negative, SpecimenResultAntibody::CONCLUSION_QUANT_NEGATIVE_NUMBER),
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

    private function getSpecimenWellForFirstResult(Specimen $specimen): SpecimenWell
    {
        $wells = $specimen->getWells();
        if (count($wells) < 1) {
            throw new \RuntimeException(sprintf('Specimen %s is not yet on a Well Plate', $specimen->getAccessionId()));
        }

        $well = array_shift($wells);
        if ($well->getResultAntibody()) {
            throw new \RuntimeException(sprintf('Antibody results already present on Specimen %s in Well %s on Well Plate %s', $specimen->getAccessionId(), $well->getPositionAlphanumeric(), $well->getWellPlateBarcode()));
        }

        return $well;
    }

    private function getNextPositionForPlate(WellPlate $plate): string
    {
        do {
            $barcode = $plate->getBarcode();

            if (!isset($this->platePositions[$barcode])) {
                $this->platePositions[$barcode] = 0;
            }

            // Get next position
            $this->platePositions[$barcode]++;

            $position = SpecimenWell::positionAlphanumericFromInt($this->platePositions[$barcode]);
        } while (null !== $plate->getWellAtPosition($position));

        return $position;
    }
}

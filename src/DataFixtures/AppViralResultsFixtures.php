<?php

namespace App\DataFixtures;

use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\SpecimenResultQPCR;
use App\Entity\SpecimenWell;
use App\Entity\WellPlate;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

class AppViralResultsFixtures extends Fixture implements DependentFixtureInterface
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
            AppSalivaTubeFixtures::class,
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
                    $specimen = $this->getRandomSpecimenPendingResultsForGroup($em, $group);

                    // Might not have enough fixture Tubes to keep going
                    if (!$specimen) continue;

                    // Set a random conclusion, if we have one
                    $conclusion = $possibleResults[array_rand($possibleResults)];
                    if ($conclusion) {
                        $resultDate = new \DateTimeImmutable(sprintf('-%d days', $day));

                        $well = $this->getSpecimenWellForFirstResult($specimen);

                        // Add Result to Well
                        $result = new SpecimenResultQPCR($well, $conclusion);
                        $result->setCreatedAt($resultDate);

                        // Set Position normally coming from reporting result
                        $well->setPositionAlphanumeric($this->getNextPositionForPlate($well->getWellPlate()));

                        $em->persist($result);
                    }
                }
            }
        }
    }

    /**
     * Build array of possible Results across a probability distribution.
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
            ->join('s.wells', 'wells')

            // Group
            ->andWhere('s.participantGroup = :group')
            ->setParameter('group', $group)

            // Has been accepted by a Check-in Technician
            ->andWhere('s.status = :status')
            ->setParameter('status', Specimen::STATUS_ACCEPTED)

            // Is on a Well Plate
            ->andWhere('wells.wellPlate IS NOT NULL')

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

    private function getSpecimenWellForFirstResult(Specimen $specimen): SpecimenWell
    {
        $wells = $specimen->getWells();
        if (count($wells) < 1) {
            throw new \RuntimeException(sprintf('Specimen %s is not yet on a Well Plate', $specimen->getAccessionId()));
        }

        $well = array_shift($wells);
        if ($well->getResultQPCR()) {
            throw new \RuntimeException(sprintf('Specimen %s in Well %s on Well Plate %s already has results', $specimen->getAccessionId(), $well->getPositionAlphanumeric(), $well->getWellPlateBarcode()));
        }

        return $well;
    }

    private function getNextPositionForPlate(WellPlate $plate): string
    {
        $barcode = $plate->getBarcode();

        if (!isset($this->platePositions[$barcode])) {
            $this->platePositions[$barcode] = 0;
        }

        // Get next position
        $this->platePositions[$barcode]++;

        return SpecimenWell::positionAlphanumericFromInt($this->platePositions[$barcode]);
    }
}

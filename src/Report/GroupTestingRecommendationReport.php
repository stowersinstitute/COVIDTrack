<?php

namespace App\Report;

use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\SpecimenRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Generate a recommendation for further CLIA testing for a given Participant Group.
 */
class GroupTestingRecommendationReport
{
    /** @var SpecimenRepository */
    private $specimenRepo;

    public function __construct(EntityManagerInterface $em)
    {
        $this->specimenRepo = $em->getRepository(Specimen::class);
    }

    /**
     * Get a testing recommendation for a Participant Group given the date
     * Specimen Results were reported.
     *
     * GroupTestingRecommendation object can be used as a string.
     */
    public function resultForGroup(ParticipantGroup $group, \DateTimeInterface $resultedAt): GroupTestingRecommendation
    {
        // Collect all Specimens for this group and period
        $specimens = $this->specimenRepo
            ->findByGroupForResultsPeriod($group, $resultedAt);

        return GroupTestingRecommendation::createForSpecimens($specimens);
    }
}

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
     * Get a testing recommendation for a Participant Group and the date their
     * Specimens were collected (i.e. extracted from their body.)
     *
     * GroupTestingRecommendation object can be used as a string.
     */
    public function resultForGroup(ParticipantGroup $group, \DateTimeInterface $collectionDate): GroupTestingRecommendation
    {
        // Collect all Specimens for this group and period
        $specimens = $this->specimenRepo
            ->findByGroupForCollectionPeriod($group, $collectionDate);

        return GroupTestingRecommendation::createForSpecimens($specimens);
    }
}

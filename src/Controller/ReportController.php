<?php

namespace App\Controller;

use App\Entity\ExcelImportWorkbook;
use App\Entity\AuditLog;
use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\ExcelImport\ParticipantGroupImporter;
use App\Form\GenericExcelImportType;
use App\Form\ParticipantGroupForm;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Report on maintained data.
 *
 * @Route(path="/reports")
 */
class ReportController extends AbstractController
{
    /**
     * CLIA Testing Commendations by Participant Group
     *
     * @Route(path="/group/results", methods={"GET"}, name="app_report_group_results")
     */
    public function groupResults()
    {
        $specimenRepo = $this->getDoctrine()->getRepository(Specimen::class);
        $groupRepo = $this->getDoctrine()->getRepository(ParticipantGroup::class);

        /**
         * Collect results for each group. Internal format ends up like this:
         *
         * [
         *     'Alligators' => [
         *         '2020-05-03 4:00pm' => 'Recommended',
         *         '2020-05-04 4:00pm' => 'No',
         *         '2020-05-05 4:00pm' => 'Awaiting Results',
         *     ]
         * ]
         */
        $reportData = [];

        // X axis
        /** @var \DateTime[] $collectionDates */
        $collectionDates = $specimenRepo->findAvailableGroupResultDates();
        // Y axis
        // TODO: Only Groups with results Specimens?
        $groupsWithResults = $groupRepo->findActiveAlphabetical();

        foreach ($groupsWithResults as $group) {
            /**
             * Keys: Collection Date string like "2020-05-05 4:00pm". Printed in report.
             * Values: Recommendation text string
             */
            $byDate = [];

            foreach ($collectionDates as $collectionDate) {
                // Collect all Specimens for this group and period
                $results = $specimenRepo->findByGroupForCollectionPeriod($group, $collectionDate);

                // Calculate count for each Specimen CLIA testing recommendation value
                $initial = [
                    Specimen::CLIA_REC_RECOMMENDED => 0,
                    Specimen::CLIA_REC_PENDING => 0,
                    Specimen::CLIA_REC_NO => 0,
                ];
                $count = array_reduce($results, function (array $carry, Specimen $s) {
                    $carry[$s->getCliaTestingRecommendation()]++;
                    return $carry;
                }, $initial);

                // If any specimen recommended for testing, whole group must be notified for CLIA testing
                if ($count[Specimen::CLIA_REC_RECOMMENDED] > 0) {
                    $text = Specimen::lookupCliaTestingRecommendationText(Specimen::CLIA_REC_RECOMMENDED);
                }
                // If awaiting at least one result, group results still pending
                else if ($count[Specimen::CLIA_REC_PENDING] > 0) {
                    $text = Specimen::lookupCliaTestingRecommendationText(Specimen::CLIA_REC_PENDING);
                }
                // If all report negative, CLIA testing not necessary
                else if ($count[Specimen::CLIA_REC_NO] > 0) {
                    $text = Specimen::lookupCliaTestingRecommendationText(Specimen::CLIA_REC_NO);
                }
                // Group not tested during this period
                else {
                    $text = '-';
                }

                $byDate[$collectionDate->format('Y-m-d g:ia')] = $text;
            }

            $reportData[$group->getTitle()] = $byDate;
        }

        /** @var ParticipantGroup[] $allGroups */
        $allGroups = $this->getDoctrine()
            ->getRepository(ParticipantGroup::class)
            ->findAll();

        return $this->render('reports/group-results.html.twig', [
            'allGroups' => $allGroups,
            'collectionDates' => $collectionDates,
            'reportData' => $reportData,
        ]);
    }

    private function mustFindGroup($id): ParticipantGroup
    {
        $s = $this->getDoctrine()
            ->getRepository(ParticipantGroup::class)
            ->findOneByAnyId($id);

        if (!$s) {
            throw new \InvalidArgumentException('Cannot find Participant Group');
        }

        return $s;
    }
}
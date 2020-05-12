<?php

namespace App\Controller;

use App\Entity\ExcelImportWorkbook;
use App\Entity\AuditLog;
use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\ExcelImport\ParticipantGroupImporter;
use App\Form\GenericExcelImportType;
use App\Form\ParticipantGroupForm;
use App\Report\GroupTestingRecommendationReport;
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
     * Allows running report exposed by this Controller
     *
     * @var GroupTestingRecommendationReport
     */
    private $groupTestRecReport;

    public function __construct(GroupTestingRecommendationReport $groupTestRecReport)
    {
        $this->groupTestRecReport = $groupTestRecReport;
    }

    /**
     * CLIA Testing Recommendations by Participant Group
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
         *         '2020-05-03' => 'Recommended',
         *         '2020-05-04' => 'No',
         *         '2020-05-05' => 'Awaiting Results',
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
             * Keys: Collection Date string like "2020-05-05". Printed in report.
             * Values: Recommendation text string
             */
            $byDate = [];

            foreach ($collectionDates as $collectionDate) {
                $result = $this->groupTestRecReport->resultForGroup($group, $collectionDate);

                $byDate[$collectionDate->format('Y-m-d')] = $result;
            }

            $reportData[$group->getTitle()] = $byDate;
        }

        /** @var ParticipantGroup[] $allGroups */
        $allGroups = $this->getDoctrine()
            ->getRepository(ParticipantGroup::class)
            ->findAll();

        return $this->render('reports/group-results/index.html.twig', [
            'allGroups' => $allGroups,
            'collectionDates' => $collectionDates,
            'reportData' => $reportData,
        ]);
    }
}

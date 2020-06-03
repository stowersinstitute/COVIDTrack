<?php

namespace App\Controller;

use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\StudyCoordinatorNotification;
use App\Report\GroupTestingRecommendationReport;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Report on maintained data.
 *
 * @Route(path="/reports")
 */
class ReportController extends AbstractController
{
    /**
     * List notifications previously sent to the Study Coordinator that
     * recommend Participant Groups with a positive test should undergo
     * further testing.
     *
     * @Route(path="/coordinator/notifications", methods={"GET"}, name="report_coordinator_notifications")
     */
    public function coordinatorNotifications()
    {
        $this->denyAccessUnlessGranted('ROLE_REPORTS_GROUP_VIEW');

        $logs = $this->getDoctrine()
            ->getRepository(StudyCoordinatorNotification::class)
            ->findBy([], ['createdAt' => 'DESC']);

        return $this->render('reports/coordinator-notifications/index.html.twig', [
            'logs' => $logs,
        ]);
    }

    /**
     * CLIA Testing Recommendations by Participant Group
     *
     * @Route(path="/group/results", methods={"GET"}, name="app_report_group_results")
     */
    public function groupResults(GroupTestingRecommendationReport $groupTestRecReport)
    {
        $this->denyAccessUnlessGranted('ROLE_REPORTS_GROUP_VIEW');

        $specimenRepo = $this->getDoctrine()->getRepository(Specimen::class);
        $groupRepo = $this->getDoctrine()->getRepository(ParticipantGroup::class);

        /**
         * Roll-up testing recommendation for each Participant Group.
         * Internal format ends up like this:
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
        /** @var \DateTime[] $resultDates */
        $resultDates = $specimenRepo->findAvailableGroupResultDates();

        // Y axis
        $groups = $groupRepo->findActiveAlphabetical();
        foreach ($groups as $group) {
            /**
             * Keys: Results Date string like "2020-05-05". Printed in report.
             * Values: Recommendation text string
             */
            $byDate = [];

            foreach ($resultDates as $resultDate) {
                $result = $groupTestRecReport->resultForGroup($group, $resultDate);

                $byDate[$resultDate->format('Y-m-d')] = $result;
            }

            $reportData[$group->getTitle()] = $byDate;
        }

        /** @var ParticipantGroup[] $allGroups */
        $allGroups = $this->getDoctrine()
            ->getRepository(ParticipantGroup::class)
            ->findAll();

        return $this->render('reports/group-results/index.html.twig', [
            'allGroups' => $allGroups,
            'resultDates' => $resultDates,
            'reportData' => $reportData,
        ]);
    }
}

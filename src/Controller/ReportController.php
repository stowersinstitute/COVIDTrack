<?php

namespace App\Controller;

use App\Command\Report\BaseResultsNotificationCommand;
use App\Entity\NonNegativeViralNotification;
use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\CliaRecommendationViralNotification;
use App\Report\GroupTestingRecommendationReport;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Report on maintained data.
 *
 * @Route(path="/reports")
 */
class ReportController extends AbstractController
{
    /**
     * List CLIA notifications previously sent that recommend certain
     * Participant Groups should undergo further testing.
     *
     * @Route(path="/email-notifications/clia", methods={"GET"}, name="report_notification_clia")
     */
    public function notificationsClia(RouterInterface $router)
    {
        // User must have one or more of these
        $this->denyAccessUnlessGranted([
            // Users who receive the notification can check it for themselves
            BaseResultsNotificationCommand::NOTIFY_USERS_WITH_ROLE_OLD,
            BaseResultsNotificationCommand::NOTIFY_USERS_WITH_ROLE,

            // Users who view reports on Groups
            'ROLE_REPORTS_GROUP_VIEW',
        ]);

        $limit = 100;
        $logs = $this->getDoctrine()
            ->getRepository(CliaRecommendationViralNotification::class)
            ->findMostRecent($limit);

        return $this->render('reports/email-notifications/index.html.twig', [
            'notification_type_text' => 'CLIA Recommendation',
            'notificationCheckUrl' => $router->generate('report_notification_clia_check'),
            'logs' => $logs,
            'limit' => $limit,
        ]);
    }

    /**
     * Run logic that would send the CLIA notification if new
     * results need to be reported.
     *
     * Meant to be called from the UI via AJAX.
     *
     * @Route(path="/email-notifications/clia/check", methods={"POST"}, name="report_notification_clia_check")
     */
    public function checkCliaNotifications(KernelInterface $kernel)
    {
        try {
            // User must have one or more of these
            $this->denyAccessUnlessGranted([
                // Users who receive the notification can check it for themselves
                BaseResultsNotificationCommand::NOTIFY_USERS_WITH_ROLE_OLD,
                BaseResultsNotificationCommand::NOTIFY_USERS_WITH_ROLE,

                // Users who can edit results
                'ROLE_RESULTS_EDIT',
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }

        // Execute Symfony Command programmatically
        $application = new Application($kernel);
        $application->setAutoExit(false);

        $commandName = 'app:report:notify-on-recommended-viral-result';
        $input = new ArrayInput([
            'command' => $commandName,
        ]);

        // Output is not used
        $output = new NullOutput();
        $exitCode = $application->run($input, $output);

        $success = true;
        $message = 'Check for new results recommending additional CLIA testing complete';
        if ($exitCode !== 0) {
            $success = false;
            $message = 'Error occurred when checking for new results';
        }

        return $this->json([
            'success' => $success,
            'message' => $message,
        ]);
    }

    /**
     * List Non-Negative Notifications previously sent.
     *
     * @Route(path="/email-notifications/non-negative", methods={"GET"}, name="report_notification_non_negative")
     */
    public function notificationsNonNegative(RouterInterface $router)
    {
        // User must have one or more of these
        $this->denyAccessUnlessGranted([
            // Users who receive the notification can check it for themselves
            BaseResultsNotificationCommand::NOTIFY_USERS_WITH_ROLE_OLD,
            BaseResultsNotificationCommand::NOTIFY_USERS_WITH_ROLE,

            // Users who view reports on Groups
            'ROLE_REPORTS_GROUP_VIEW',
        ]);

        $limit = 100;
        $logs = $this->getDoctrine()
            ->getRepository(NonNegativeViralNotification::class)
            ->findMostRecent($limit);

        return $this->render('reports/email-notifications/index.html.twig', [
            'notification_type_text' => 'Non-Negative',
            'notificationCheckUrl' => $router->generate('report_notification_non_negative_check'),
            'logs' => $logs,
            'limit' => $limit,
        ]);
    }

    /**
     * Run logic that would send the Non-Negative Viral notification if new
     * results need to be reported.
     *
     * Meant to be called from the UI via AJAX.
     *
     * @Route(path="/email-notifications/non-negative/check", methods={"POST"}, name="report_notification_non_negative_check")
     */
    public function checkNonNegativeNotifications(KernelInterface $kernel)
    {
        try {
            // User must have one or more of these
            $this->denyAccessUnlessGranted([
                // Users who receive the notification can check it for themselves
                BaseResultsNotificationCommand::NOTIFY_USERS_WITH_ROLE_OLD,
                BaseResultsNotificationCommand::NOTIFY_USERS_WITH_ROLE,

                // Users who can edit results
                'ROLE_RESULTS_EDIT',
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }

        // Execute Symfony Command programmatically
        $application = new Application($kernel);
        $application->setAutoExit(false);

        $commandName = 'app:report:notify-on-non-negative-viral-result';
        $input = new ArrayInput([
            'command' => $commandName,
        ]);

        // Output is not used
        $output = new NullOutput();
        $exitCode = $application->run($input, $output);

        $success = true;
        $message = 'Check for new Non-Negative Viral Results complete';
        if ($exitCode !== 0) {
            $success = false;
            $message = 'Error occurred when checking for new results';
        }

        return $this->json([
            'success' => $success,
            'message' => $message,
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
        $resultDates = $specimenRepo->findAvailableGroupViralResultDates();

        // Y axis
        $groups = $groupRepo->findActive();
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
            ->findBy([], ['title' => 'ASC']);

        return $this->render('reports/group-results/index.html.twig', [
            'allGroups' => $allGroups,
            'resultDates' => $resultDates,
            'reportData' => $reportData,
        ]);
    }
}

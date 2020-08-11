<?php

namespace App\Command\Report;

use App\Entity\CliaRecommendationViralNotification;
use App\Entity\ParticipantGroup;
use App\Entity\SpecimenResultQPCR;
use App\Entity\NonNegativeViralNotification;
use App\Util\DateUtils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Router;

/**
 * Notifies privileged users when a new Non-Negative Viral Result is available.
 *
 * NOTE: This Command runs on a recurring scheduled via App\Scheduled\ScheduledTasks
 */
class NotifyOnNonNegativeViralResultCommand extends BaseResultsNotificationCommand
{
    /**
     * Holds local cache of results when run. Reset when invoking execute().
     */
    private $notificationData = [];

    protected static $defaultName = 'app:report:notify-on-non-negative-viral-result';

    protected function configure()
    {
        // See parent for CLI options
        parent::configure();

        $this
            ->setDescription('Notifies privileged users when a new Non-Negative Viral Result is available.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Reset local var that tracks calculated results in buildNotificationData()
        $this->notificationData = [];

        return parent::execute($input, $output);
    }

    protected function getSubject(): string
    {
        return 'New Non-Negative Viral Group Results';
    }

    /**
     * Return list of roles where if user is explicitly assigned at least one,
     * they should receive the Notification sent by this command.
     *
     * @return string[]
     */
    protected function getRolesToReceiveNotification(): array
    {
        return [
            self::NOTIFY_USERS_WITH_ROLE,
        ];
    }

    protected function getHtmlEmailBody(): string
    {
        $recommendations = $this->buildNotificationData();

        $groupsRecTestingOutput = array_map(function(array $tupleResult) {
            /** @var ParticipantGroup $group */
            /** @var \DateTimeInterface $updatedAt */
            list($group, $updatedAt) = $tupleResult;

            return sprintf('<tr><td>%s</td><td>%s</td></tr>', htmlentities($group->getTitle()), $updatedAt->format(self::RESULTS_DATETIME_FORMAT));
        }, $recommendations);

        $url = $this->router->generate('index', [], Router::ABSOLUTE_URL);
        $html = sprintf("
            <p>Non-Negative viral results have been reported for these Participant Groups:</p>

            <table class='results-table'>
                <thead>
                    <tr>
                         <th>Group</th>
                         <th>Results Published</th>
                    </tr>
                </thead>
                <tbody>
%s
                </tbody>
            </table>

            <p>
                View more details in COVIDTrack:<br>%s
            </p>

<style>
.results-table {
    border:1px solid #333;
}
.results-table th {
    text-align:left;
}
.results-table td, .results-table th {
    padding:0.25em;
    border-bottom: 1px solid #ccc;
}
</style>
",
            implode("\n", $groupsRecTestingOutput),
            sprintf('<a href="%s">%s</a>', htmlentities($url), htmlentities($url))
        );

        return $html;
    }

    /**
     * Get Participant Groups and the timestamp when their result was reported.
     *
     * Usage:
     *
     * $results = $this->getNewResults();
     * foreach ($results as $result) {
     *     list($group, $updatedAt) = $result;
     * }
     *
     * Each tuple result contains this data:
     * [0] === ParticipantGroup[] Participant Group with Non-Negative Viral result
     * [1] === \DateTimeImmutable[] $resultsUploadedAt Time when most recent result for group was updated
     */
    private function buildNotificationData(): array
    {
        if (!empty($this->notificationData)) {
            return $this->notificationData;
        }

        $output = [];

        $lastNotificationSent = $this->em
            ->getRepository(NonNegativeViralNotification::class)
            ->getMostRecentSentAt();
        if ($this->input->getOption('all-groups-today')) {
            // CLI options want us to email about all groups with positive result today
            // Assume since midnight today
            $lastNotificationSent = DateUtils::dayFloor(new \DateTime());
        } else if ($this->input->getOption('all-groups-ever') || !$lastNotificationSent) {
            // Email Notification not yet sent
            // Search for since earliest possible date
            $lastNotificationSent = new \DateTimeImmutable('2020-01-01 00:00:00');
        }

        $this->outputDebug('Searching for new non-negative viral results created or updated since ' . $lastNotificationSent->format("Y-m-d H:i:s"));

        /** @var SpecimenResultQPCR[] $results */
        $results = $this->em
            ->getRepository(SpecimenResultQPCR::class)
            ->findTestingResultNonNegativeUpdatedAfter($lastNotificationSent);
        if (!$results) {
            return $output;
        }

        $this->outputDebug('Found new non-negative viral results: ' . count($results));

        // Calculate results
        foreach ($results as $result) {
            $group = $result->getSpecimen()->getParticipantGroup();
            $resultUpdatedAt = $result->getUpdatedAt() ?: new \DateTimeImmutable();

            // Indexing by ParticipantGroup.id ensures Group only displays once in output
            $output[$group->getId()] = [$group, $resultUpdatedAt];
        }

        // Remove Groups already notified today
        if (!$this->input->getOption('all-groups-today') && !$this->input->getOption('all-groups-ever')) {
            $now = new \DateTime();

            // Remove Groups already notified today for Non-Negative
            $groupsNotifiedToday = $this->em
                ->getRepository(NonNegativeViralNotification::class)
                ->getGroupsNotifiedOnDate($now);

            // Also remove Groups notified for Recommended CLIA testing,
            // so don't notify about a Non-Negative when already notified about Recommended
            /** @var ParticipantGroup[] $groupsRecommended */
            $groupsRecommended = $this->em
                ->getRepository(CliaRecommendationViralNotification::class)
                ->getGroupsNotifiedOnDate($now);

            $groupsToRemove = array_merge($groupsNotifiedToday, $groupsRecommended);

            foreach ($groupsToRemove as $removeGroup) {
                unset($output[$removeGroup->getId()]);
            }
        }

        $this->notificationData = array_values($output);

        return $this->notificationData;
    }

    protected function getReasonToNotSend(): ?string
    {
        $groups = [];
        foreach ($this->buildNotificationData() as $resultTuple) {
            list($group, $timestamp) = $resultTuple;
            $groups[] = $group;
        }

        if (count($groups) < 1) {
            return 'No new Participant Groups to notify about';
        }

        return null;
    }

    protected function logSentEmail(Email $email)
    {
        $save = !$this->input->getOption('skip-saving');
        if (!$save) {
            return;
        }

        $groups = [];
        foreach ($this->buildNotificationData() as $resultTuple) {
            list($group, $timestamp) = $resultTuple;
            $groups[] = $group;
        }

        $notif = NonNegativeViralNotification::createFromEmail($email, $groups);
        $this->em->persist($notif);
        $this->em->flush();
    }
}

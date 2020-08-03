<?php

namespace App\Command\Report;

use App\Entity\AntibodyNotification;
use App\Entity\ParticipantGroup;
use App\Entity\SpecimenResultAntibody;
use App\Util\DateUtils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Router;

/**
 * Notifies privileged users when a Antibody Result is available whose
 * result is not negative.
 *
 * NOTE: This Command runs on a recurring scheduled via App\Scheduled\ScheduledTasks
 */
class NotifyOnAntibodyResultsCommand extends BaseResultsNotificationCommand
{
    protected static $defaultName = 'app:report:notify-on-antibody-result';

    /**
     * Holds local cache of results when run. Reset when invoking execute().
     */
    private $notificationData = [];

    protected function configure()
    {
        // See parent for CLI options
        parent::configure();

        $this
            ->setDescription('Notifies privileged users when Antibody Results are available that are not Negative results.')
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
        return 'New Antibody Results';
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
            self::NOTIFY_USERS_WITH_ROLE_ANTIBODY,
        ];
    }

    protected function getHtmlEmailBody(): string
    {
        $results = $this->getNewResults();

        $groupResultsHtmlLines = array_map(function(array $tupleResult) {
            /** @var ParticipantGroup $group */
            /** @var \DateTimeInterface $updatedAt */
            list($group, $updatedAt) = $tupleResult;

            return sprintf('<tr><td>%s</td><td>%s</td></tr>', $group->getTitle(), $updatedAt->format(self::RESULTS_DATETIME_FORMAT));
        }, $results);

        $url = $this->router->generate('index', [], Router::ABSOLUTE_URL);
        $html = sprintf("
            <p>One or more members of these Participant Groups had a non-negative or stronger antibody response in recent results:</p>

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
            implode("\n", $groupResultsHtmlLines),
            sprintf('<a href="%s">%s</a>', htmlentities($url), $url)
        );

        return $html;
    }

    protected function getReasonToNotSend(): ?string
    {
        $results = $this->getNewResults();
        if (count($results) < 1) {
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
        foreach ($this->getNewResults() as $resultTuple) {
            list($group, $timestamp) = $resultTuple;
            $groups[] = $group;
        }

        $notif = AntibodyNotification::createFromEmail($email, $groups);
        $this->em->persist($notif);
        $this->em->flush();
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
     * [0] === ParticipantGroup[] Participant Group with not Negative Antibody result
     * [1] === \DateTimeImmutable[] $resultsUploadedAt Time when most recent result for group was updated
     */
    private function getNewResults(): array
    {
        if (!empty($this->notificationData)) {
            return $this->notificationData;
        }

        $output = [];

        $lastNotificationSent = $this->em
            ->getRepository(AntibodyNotification::class)
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

        $this->outputDebug('Searching Antibody results that are not Negative, created or updated since ' . $lastNotificationSent->format("Y-m-d H:i:s"));

        /** @var SpecimenResultAntibody[] $results */
        $results = $this->em
            ->getRepository(SpecimenResultAntibody::class)
            ->findAnyResultNotNegativeAfter($lastNotificationSent);
        if (!$results) {
            return $output;
        }

        $this->outputDebug('Found Antibody results: ' . count($results));

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
            $groupsNotifiedToday = $this->em
                ->getRepository(AntibodyNotification::class)
                ->getGroupsNotifiedOnDate($now);

            foreach ($groupsNotifiedToday as $groupPreviouslyNotified) {
                unset($output[$groupPreviouslyNotified->getId()]);
            }
        }

        $this->notificationData = array_values($output);

        return $this->notificationData;
    }
}

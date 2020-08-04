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
        $groups = $recommendations['groups'];
        $timestamps = $recommendations['timestamps'];

        $groupsRecTestingOutput = array_map(function(ParticipantGroup $g) {
            return sprintf('<li>%s</li>', htmlentities($g->getTitle()));
        }, $groups);

        $timestampsOutput = array_map(function(\DateTimeImmutable $dt) {
            return sprintf("<li>%s</li>", $dt->format(self::RESULTS_DATETIME_FORMAT));
        }, $timestamps);

        $url = $this->router->generate('index', [], Router::ABSOLUTE_URL);
        $html = sprintf("
            <p>Non-Negative viral results have been reported for these Participant Groups:</p>

            <p>Results published:</p>
            <ul>
%s
            </ul>

            <p>Participant Groups:</p>
            <ul>
%s
            </ul>

            <p>
                View more details in COVIDTrack:<br>%s
            </p>
        ",
            implode("\n", $timestampsOutput),
            implode("\n", $groupsRecTestingOutput),
            sprintf('<a href="%s">%s</a>', htmlentities($url), htmlentities($url))
        );

        return $html;
    }

    /**
     * Get Participant Groups and the timestamp when the non-negative viral result
     * was uploaded.
     *
     * Usage:
     *
     * $data = $this->buildNotificationData();
     * $groups = $data['groups'];
     * $timestamps = $data['timestamps'];
     *
     * $groups === ParticipantGroup[] - Unique list of groups with non-negative viral result
     * $resultsUploadedAt === \DateTimeImmutable[] - Unique list of times found results were uploaded
     */
    private function buildNotificationData(): array
    {
        $this->notificationData = [
            'groups' => [],
            'timestamps' => [],
        ];

        $lastNotificationSent = $this->em
            ->getRepository(NonNegativeViralNotification::class)
            ->getMostRecentSentAt();
        if ($this->input->getOption('all-groups-today')) {
            // CLI options want us to email about all groups with positive result today
            // Assume since midnight today
            $lastNotificationSent = DateUtils::dayFloor(new \DateTime());
        } else if ($this->input->getOption('all-groups-ever') || !$lastNotificationSent) {
            // Email never sent
            // Search for since earliest possible date
            $lastNotificationSent = new \DateTimeImmutable('2020-01-01 00:00:00');
        }

        $this->outputDebug('Searching for new non-negative viral results since ' . $lastNotificationSent->format("Y-m-d H:i:s"));

        /** @var SpecimenResultQPCR[] $results */
        $results = $this->em
            ->getRepository(SpecimenResultQPCR::class)
            ->findTestingResultNonNegativeUpdatedAfter($lastNotificationSent);
        if (!$results) {
            return $this->notificationData;
        }

        $this->outputDebug('Found new non-negative viral results: ' . count($results));

        // Build Group data
        $groups = [];
        foreach ($results as $result) {
            $group = $result->getSpecimen()->getParticipantGroup();
            $groups[$group->getId()] = $group;
        }

        // Build Timestamp data
        $resultsTimestamps = [];
        foreach ($results as $result) {
            // Result timestamp
            $timestamp = $result->getUpdatedAt();
            if (!$timestamp) {
                continue;
            }

            $idx = $timestamp->format(self::RESULTS_DATETIME_FORMAT);
            $resultsTimestamps[$idx] = $timestamp;
        }

        // Remove Groups already notified today
        if (!$this->input->getOption('all-groups-today') && !$this->input->getOption('all-groups-ever')) {
            $now = new \DateTime();

            // Groups notified due to Non-Negative
            /** @var ParticipantGroup[] $groupsNonNegative */
            $groupsNonNegative = $this->em
                ->getRepository(NonNegativeViralNotification::class)
                ->getGroupsNotifiedOnDate($now);

            // Also remove Groups notified of Recommended CLIA testing,
            // so don't notify about a Non-Negative when already notified about Recommended
            /** @var ParticipantGroup[] $groupsRecommended */
            $groupsRecommended = $this->em
                ->getRepository(CliaRecommendationViralNotification::class)
                ->getGroupsNotifiedOnDate($now);

            $groupsToRemove = array_merge($groupsNonNegative, $groupsRecommended);
            foreach ($groupsToRemove as $groupPreviouslyNotified) {
                $id = $groupPreviouslyNotified->getId();
                unset($groups[$id]);
            }
        }

        // Remaining are Groups not yet notified about a Recommended or Non-Negative result today
        $output['groups'] = array_values($groups);
        $output['timestamps'] = array_values($resultsTimestamps);

        return $output;
    }

    protected function getReasonToNotSend(): ?string
    {
        $report = $this->buildNotificationData();
        $groups = $report['groups'];

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

        $report = $this->buildNotificationData();
        $groups = $report['groups'];

        $notif = NonNegativeViralNotification::createFromEmail($email, $groups);
        $this->em->persist($notif);
        $this->em->flush();
    }
}

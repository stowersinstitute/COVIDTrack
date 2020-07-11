<?php

namespace App\Command\Report;

use App\Entity\ParticipantGroup;
use App\Entity\SpecimenResultQPCR;
use App\Entity\StudyCoordinatorNotification;
use App\Util\DateUtils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Router;

/**
 * Notifies users that should be notified when a new Non-Negative Result is available.
 *
 * NOTE: This Command runs on a recurring scheduled via App\Scheduled\ScheduledTasks
 */
class NotifyOnNonNegativeResultCommand extends BaseResultsNotificationCommand
{
    protected static $defaultName = 'app:report:notify-on-non-negative-result';

    protected function configure()
    {
        $this
            ->setDescription('Notifies users that should be notified when a new Positive Result is available.')
            ->addOption('all-non-negative-groups-today', null, InputOption::VALUE_NONE, 'Use to notify about all Groups with a non-negative result published today')
            ->addOption('all-non-negative-groups-ever', null, InputOption::VALUE_NONE, 'Use to notify about all Groups with a non-negative result published from the beginning of time')
        ;
    }

    protected function getSubject(): string
    {
        return 'Non-Negative Group Results';
    }

    protected function getHtmlEmailBody(): string
    {
        $recommendations = $this->getGroupsWithTimestamps();
        $groups = $recommendations['groups'];
        $timestamps = $recommendations['timestamps'];

        $groupsRecTestingOutput = array_map(function(ParticipantGroup $g) {
            return sprintf('<li>%s</li>', $g->getTitle());
        }, $groups);

        $timestampsOutput = array_map(function(\DateTimeImmutable $dt) {
            return sprintf("<li>%s</li>", $dt->format(self::RESULTS_DATETIME_FORMAT));
        }, $timestamps);

        $url = $this->router->generate('index', [], Router::ABSOLUTE_URL);
        $html = sprintf("
            <p>Non-Negative results have been reported for these Participant Groups:</p>

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
            sprintf('<a href="%s">%s</a>', htmlentities($url), $url)
        );

        return $html;
    }

    /**
     * Get Participant Groups and the timestamp when their recommended result
     * was uploaded.
     *
     * Usage:
     *
     * $rec = $this->getGroupsWithTimestamps();
     * $groups = $rec['groups'];
     * $timestamps = $rec['timestamps'];
     *
     * $groups === ParticipantGroup[] - Unique list of groups recommended for further testing
     * $resultsUploadedAt === \DateTimeImmutable[] - Unique list of times found results were uploaded
     */
    private function getGroupsWithTimestamps(): array
    {
        // Ensure this method only runs once
        if (!empty($output)) {
            return $output;
        }

        static $output = [
            'groups' => [],
            'timestamps' => [],
        ];

        $lastNotificationSent = $this->em
            ->getRepository(StudyCoordinatorNotification::class)
            ->getMostRecentSentAt();
        if ($this->input->getOption('all-positive-groups-today')) {
            // CLI options want us to email about all groups with positive result today
            // Assume since midnight today
            $lastNotificationSent = DateUtils::dayFloor(new \DateTime());
        } else if ($this->input->getOption('all-positive-groups-ever') || !$lastNotificationSent) {
            // Study Coordinator never notified
            // Search for since earliest possible date
            $lastNotificationSent = new \DateTimeImmutable('2020-01-01 00:00:00');
        }

        $this->outputDebug('Searching for new recommended results since ' . $lastNotificationSent->format("Y-m-d H:i:s"));

        /** @var SpecimenResultQPCR[] $results */
        $results = $this->em
            ->getRepository(SpecimenResultQPCR::class)
            ->findTestingRecommendedResultCreatedAfter($lastNotificationSent);
        if (!$results) {
            return $output;
        }

        $this->outputDebug('Found new recommended results: ' . count($results));

        // Get recommendations for testing
        $groups = [];
        $resultsTimestamps = [];
        foreach ($results as $result) {
            // Group
            $group = $result->getSpecimen()->getParticipantGroup();
            $groups[$group->getId()] = $group;

            // Result timestamp
            $timestamp = $result->getCreatedAt();
            if ($timestamp) {
                $idx = $timestamp->format(self::RESULTS_DATETIME_FORMAT);
                $resultsTimestamps[$idx] = $timestamp;
            }
        }

        // Remove Groups already notified today
        if (!$this->input->getOption('all-positive-groups-today') && !$this->input->getOption('all-positive-groups-ever')) {
            $now = new \DateTime();
            /** @var StudyCoordinatorNotification[] $groupsNotifiedToday */
            $groupsNotifiedToday = $this->em
                ->getRepository(StudyCoordinatorNotification::class)
                ->getGroupsNotifiedOnDate($now);

            foreach ($groupsNotifiedToday as $groupPreviouslyNotified) {
                $id = $groupPreviouslyNotified->getId();
                unset($groups[$id]);
            }
        }

        // Remaining are Groups the Study Coordinator has
        // not been notified about yet today
        $output['groups'] = array_values($groups);
        $output['timestamps'] = array_values($resultsTimestamps);

        return $output;
    }

    protected function getReasonToNotSend(): ?string
    {
        $recommendations = $this->getGroupsWithTimestamps();
        $groups = $recommendations['groups'];

        if (count($groups) < 1) {
            return 'No new Participant Groups need contacting';
        }

        return null;
    }

    protected function logSentEmail(Email $email)
    {
        $save = !$this->input->getOption('skip-saving');
        if (!$save) {
            return;
        }

        $recommendations = $this->getGroupsWithTimestamps();
        $groups = $recommendations['groups'];

        $notif = StudyCoordinatorNotification::createFromEmail($email, $groups);
        $this->em->persist($notif);
        $this->em->flush();
    }
}

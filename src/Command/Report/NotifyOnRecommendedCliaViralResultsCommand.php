<?php

namespace App\Command\Report;

use App\Entity\ParticipantGroup;
use App\Entity\SpecimenResultQPCR;
use App\Entity\CliaRecommendationViralNotification;
use App\Util\DateUtils;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Router;

/**
 * Notifies privileged users when a new Viral Result recommends members of its
 * Participant Group undergo addition CLIA testing.
 *
 * NOTE: This Command runs on a recurring scheduled via App\Scheduled\ScheduledTasks
 */
class NotifyOnRecommendedCliaViralResultsCommand extends BaseResultsNotificationCommand
{
    protected static $defaultName = 'app:report:notify-on-recommended-viral-result';

    protected function configure()
    {
        // See parent for CLI options
        parent::configure();

        $this
            ->setDescription('Notifies privileged users when a Viral Result recommends CLIA testing.')
        ;
    }

    protected function getSubject(): string
    {
        return 'New Group Testing Recommendation';
    }

    protected function getHtmlEmailBody(): string
    {
        $recommendations = $this->getNewTestingRecommendations();
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
            <p>Participant Groups have been recommended for diagnostic testing.</p>

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

    protected function getReasonToNotSend(): ?string
    {
        $recommendations = $this->getNewTestingRecommendations();
        $groups = $recommendations['groups'];

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

        $recommendations = $this->getNewTestingRecommendations();
        $groups = $recommendations['groups'];

        $notif = CliaRecommendationViralNotification::createFromEmail($email, $groups);
        $this->em->persist($notif);
        $this->em->flush();
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
    private function getNewTestingRecommendations(): array
    {
        $output = [
            'groups' => [],
            'timestamps' => [],
        ];

        $lastNotificationSent = $this->em
            ->getRepository(CliaRecommendationViralNotification::class)
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

        $this->outputDebug('Searching for new recommended results since ' . $lastNotificationSent->format("Y-m-d H:i:s"));

        /** @var SpecimenResultQPCR[] $results */
        $results = $this->em
            ->getRepository(SpecimenResultQPCR::class)
            ->findTestingRecommendedResultUpdatedAfter($lastNotificationSent);
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
            $timestamp = $result->getUpdatedAt();
            if ($timestamp) {
                $idx = $timestamp->format(self::RESULTS_DATETIME_FORMAT);
                $resultsTimestamps[$idx] = $timestamp;
            }
        }

        // Remove Groups already notified today
        if (!$this->input->getOption('all-groups-today') && !$this->input->getOption('all-groups-ever')) {
            $now = new \DateTime();
            $groupsNotifiedToday = $this->em
                ->getRepository(CliaRecommendationViralNotification::class)
                ->getGroupsNotifiedOnDate($now);

            foreach ($groupsNotifiedToday as $groupPreviouslyNotified) {
                $id = $groupPreviouslyNotified->getId();
                unset($groups[$id]);
            }
        }

        // Remaining are Groups not yet included in this Email Notification today
        $output['groups'] = array_values($groups);
        $output['timestamps'] = array_values($resultsTimestamps);

        return $output;
    }
}

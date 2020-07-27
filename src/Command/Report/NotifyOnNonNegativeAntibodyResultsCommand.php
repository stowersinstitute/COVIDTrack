<?php

namespace App\Command\Report;

use App\Entity\AntibodyNotification;
use App\Entity\ParticipantGroup;
use App\Entity\SpecimenResultAntibody;
use App\Util\DateUtils;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Router;

/**
 * Notifies privileged users when a Antibody Result is available whose
 * result is not negative.
 *
 * NOTE: This Command runs on a recurring scheduled via App\Scheduled\ScheduledTasks
 */
class NotifyOnNonNegativeAntibodyResultsCommand extends BaseResultsNotificationCommand
{
    protected static $defaultName = 'app:report:notify-on-antibody-result';

    protected function configure()
    {
        // See parent for CLI options
        parent::configure();

        $this
            ->setDescription('Notifies privileged users when Antibody Results are available that are not Negative results.')
        ;
    }

    protected function getSubject(): string
    {
        return 'New Antibody Results';
    }

    protected function getHtmlEmailBody(): string
    {
        $recommendations = $this->getNewResults();
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
            <p>New Antibody Results:</p>

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
        $recommendations = $this->getNewResults();
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

        $recommendations = $this->getNewResults();
        $groups = $recommendations['groups'];

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
     * $groups = $results['groups'];
     * $timestamps = $results['timestamps'];
     *
     * $groups === ParticipantGroup[] - Unique list of groups recommended for further testing
     * $resultsUploadedAt === \DateTimeImmutable[] - Unique list of times found results were uploaded
     */
    private function getNewResults(): array
    {
        $output = [
            'groups' => [],
            'timestamps' => [],
        ];

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
        // TODO: Can return results as tuple: [$group, $timestamp]?
        $groups = [];
        $resultsTimestamps = [];
        foreach ($results as $result) {
            // Group
            $group = $result->getSpecimen()->getParticipantGroup();
            $groups[$group->getId()] = $group;

            // Result timestamp
            $timestamp = $result->getUpdatedAt();
            if ($timestamp) {
                $resultsTimestamps[$group->getId()] = $timestamp;
            }
        }

        // Remove Groups already notified today
        if (!$this->input->getOption('all-groups-today') && !$this->input->getOption('all-groups-ever')) {
            $now = new \DateTime();
            $groupsNotifiedToday = $this->em
                ->getRepository(AntibodyNotification::class)
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

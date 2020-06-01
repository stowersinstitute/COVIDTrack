<?php

namespace App\Command\Report;

use App\Email\EmailBuilder;
use App\Entity\AppUser;
use App\Entity\ParticipantGroup;
use App\Entity\SpecimenResultQPCR;
use App\Entity\StudyCoordinatorNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\RouterInterface;

/**
 * Notifies users that should be notified when a new Positive Result is available.
 *
 * NOTE: This Command runs on a recurring scheduled via App\Scheduled\ScheduledTasks
 */
class NotifyOnPositiveResultCommand extends Command
{
    /**
     * Users who explicitly have this role will be notified.
     */
    const NOTIFY_USERS_WITH_ROLE = 'ROLE_NOTIFY_GROUP_RECOMMENDED_TESTING';

    /**
     * Date format for printing results in email
     */
    const RESULTS_DATETIME_FORMAT = 'F j, Y @ g:ip';

    protected static $defaultName = 'app:report:notify-on-positive-result';

    /** @var EntityManagerInterface */
    private $em;

    /** @var MailerInterface */
    private $mailer;

    /** @var RouterInterface */
    private $router;

    /** @var InputInterface */
    private $input;

    /** @var OutputInterface */
    private $output;

    public function __construct(EntityManagerInterface $em, MailerInterface $mailer, RouterInterface $router)
    {
        parent::__construct();

        $this->em = $em;
        $this->mailer = $mailer;
        $this->router = $router;
    }

    protected function configure()
    {
        $this
            ->setDescription('Notifies users that should be notified when a new Positive Result is available.')
            ->addOption('skip-saving', null, InputOption::VALUE_NONE, 'Whether to save a record of this notification being sent. Useful when testing.')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
    }

    private function outputDebug(string $line)
    {
        if ($this->output->isVerbose()) {
            $this->output->writeln($line);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $recipients = $this->getEmailRecipients();
        if (empty($recipients)) {
            $this->outputDebug('No email recipients');
            return 0;
        }

        $recommendations = $this->getNewTestingRecommendations();
        $groups = $recommendations['groups'];
        $timestamps = $recommendations['timestamps'];

        if (count($groups) < 1) {
            $this->outputDebug('No new Participant Groups need contacting');
            return 0;
        }

        $groupsRecTestingOutput = array_map(function(ParticipantGroup $g) {
            return sprintf('<li>%s</li>', $g->getTitle());
        }, $groups);

        $timestampsOutput = array_map(function(\DateTimeImmutable $dt) {
            return sprintf("<li>%s</li>", $dt->format(self::RESULTS_DATETIME_FORMAT));
        }, $timestamps);

        $subject = 'New Group Testing Recommendation';
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
        ", implode("\n", $timestampsOutput), implode("\n", $groupsRecTestingOutput), $url);

        $email = EmailBuilder::createHtml($recipients, $subject, $html);

        // Debug output email
        $this->outputDebug('------');
        $fromOutput = array_map(function(Address $A) { return $A->toString(); }, $email->getFrom());
        $toOutput = array_map(function(Address $A) { return $A->toString(); }, $email->getTo());
        $this->outputDebug('From: ' . implode(', ', $fromOutput));
        $this->outputDebug('To: ' . implode(', ', $toOutput));
        $this->outputDebug('Subject: ' . $email->getSubject());
        $this->outputDebug('------');
        $this->outputDebug($email->getHtmlBody());

        $this->mailer->send($email);

        // Log
        $save = !$input->getOption('skip-saving');
        if ($save) {
            $notif = StudyCoordinatorNotification::createFromEmail($email, $groups);
            $this->em->persist($notif);
            $this->em->flush();
        }

        return 0;
    }

    /**
     * Get Address objects for users who should be emailed this report.
     *
     * @return Address[]
     */
    private function getEmailRecipients(): array
    {
        $users = $this->em->getRepository(AppUser::class)->findAll();

        /** @var AppUser[] $notifyUsers */
        $notifyUsers = array_filter($users, function(AppUser $u) {
            // Users without email address can't be notified
            if (!$u->getEmail()) {
                return false;
            }

            // Only users assigned a permission on their Edit User page
            return $u->hasRoleExplicit(self::NOTIFY_USERS_WITH_ROLE);
        });

        // Create Address objects accepted by Symfony Mailer
        // Email address syntax verified inside Address object
        $addr = [];
        foreach ($notifyUsers as $user) {
            try {
                $addr[] = new Address($user->getEmail(), $user->getDisplayName() ?? $user->getUsername());
            } catch (\Exception $e) {
                $this->outputDebug(sprintf('Cannot send email to invalid email address "%s"', $user));
            }
        }

        return $addr;
    }

    /**
     * Get Participant Groups and the timestamp when their recommended result
     * was uploaded.
     *
     * Usage:
     *
     * $rec = $this->getNewTestingRecommendations();
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
            ->getRepository(StudyCoordinatorNotification::class)
            ->getMostRecentSentAt();
        if (!$lastNotificationSent) {
            // Study Coordinator has never been notified.
            // Assume since earliest possible time.
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

        // Get Groups the Study Coordinator was already notified about today
        $now = new \DateTime();
        /** @var StudyCoordinatorNotification[] $groupsNotifiedToday */
        $groupsNotifiedToday = $this->em
            ->getRepository(StudyCoordinatorNotification::class)
            ->getGroupsNotifiedOnDate($now);

        // Remove Groups notified today
        foreach ($groupsNotifiedToday as $groupPreviouslyNotified) {
            $id = $groupPreviouslyNotified->getId();
            unset($groups[$id]);
        }

        // Remaining are Groups the Study Coordinator has
        // not been notified about yet today
        $output['groups'] = array_values($groups);
        $output['timestamps'] = array_values($resultsTimestamps);

        return $output;
    }
}

<?php

namespace App\Command\Report;

use App\Email\EmailBuilder;
use App\Entity\AppUser;
use App\Entity\ParticipantGroup;
use App\Entity\SpecimenResultQPCR;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\RouterInterface;

/**
 * Notifies users that should be notified when a new Positive Result is available.
 */
class NotifyOnPositiveResultCommand extends Command
{
    /**
     * Users who explicitly have this role will be notified.
     */
    const NOTIFY_USERS_WITH_ROLE = 'ROLE_NOTIFY_GROUP_RECOMMENDED_TESTING';

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

        $groups = $this->getNewGroupsRecommendedTesting();
        if (count($groups) < 1) {
            $this->outputDebug('No new Participant Groups need contacting');
            return 0;
        }

        $groupsRecTestingOutput = array_map(function(ParticipantGroup $g) {
            return sprintf('<li>%s</li>', $g->getTitle());
        }, $groups);

        // TODO: Move to a specific email class
        $subject = 'New Group Testing Recommendation';
        $html = sprintf("<p>These groups require testing:</p>\n
                <ul>
                    %s
                </ul>\n
        ", implode("\n", $groupsRecTestingOutput));

        $email = EmailBuilder::createHtml($recipients, $subject, $html);

        // Debug output
        $fromOutput = array_map(function(Address $A) { return $A->toString(); }, $email->getFrom());
        $toOutput = array_map(function(Address $A) { return $A->toString(); }, $email->getTo());
        $this->outputDebug('From: ' . implode(', ', $fromOutput));
        $this->outputDebug('To: ' . implode(', ', $toOutput));
        $this->outputDebug('Subject: ' . $email->getSubject());
        $this->outputDebug('------');
        $this->outputDebug($email->getHtmlBody());

        $this->mailer->send($email);

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
     * @return ParticipantGroup[]
     */
    private function getNewGroupsRecommendedTesting(): array
    {
        // TODO: Only report on same group once per day
        /** @var SpecimenResultQPCR[] $results */
        $results = $this->em
            ->getRepository(SpecimenResultQPCR::class)
            ->findTestingRecommendedResultCreatedAfter(new \DateTimeImmutable('-5 minutes'));

        $groups = [];
        foreach ($results as $result) {
            $group = $result->getSpecimen()->getParticipantGroup();
            $groups[$group->getAccessionId()] = $group;
        }

        return array_values($groups);
    }
}

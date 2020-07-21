<?php

namespace App\Command\Report;

use App\Email\EmailBuilder;
use App\Entity\AppUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\RouterInterface;

/**
 * Common functionality for sending Notifications based on Results.
 */
abstract class BaseResultsNotificationCommand extends Command
{
    /**
     * Old role for Users who explicitly have this role will be notified.
     * @deprecated Replace with NOTIFY_USERS_WITH_ROLE TODO: CVDLS-158
     */
    const NOTIFY_USERS_WITH_ROLE_OLD = 'ROLE_NOTIFY_GROUP_RECOMMENDED_TESTING';

    /**
     * Users who explicitly have this role will be notified about Viral Results.
     */
    const NOTIFY_USERS_WITH_ROLE = 'ROLE_NOTIFY_ABOUT_VIRAL_RESULTS';

    /**
     * Date format for printing results in email
     */
    const RESULTS_DATETIME_FORMAT = 'F j, Y @ g:ia';

    /** @var EntityManagerInterface */
    protected $em;

    /** @var EmailBuilder */
    protected $emailBuilder;

    /** @var MailerInterface */
    protected $mailer;

    /** @var RouterInterface */
    protected $router;

    /** @var InputInterface */
    protected $input;

    /** @var OutputInterface */
    protected $output;

    public function __construct(EntityManagerInterface $em, EmailBuilder $emailBuilder, MailerInterface $mailer, RouterInterface $router)
    {
        parent::__construct();

        $this->em = $em;
        $this->emailBuilder = $emailBuilder;
        $this->mailer = $mailer;
        $this->router = $router;
    }

    protected function configure()
    {
        $this
            ->addOption('do-not-send', null, InputOption::VALUE_NONE, 'Do not send real email when run')
            ->addOption('skip-saving', null, InputOption::VALUE_NONE, 'Whether to save a record of this notification being sent')
            ->addOption('all-groups-today', null, InputOption::VALUE_NONE, 'Use to notify about all Groups with a non-negative result published today')
            ->addOption('all-groups-ever', null, InputOption::VALUE_NONE, 'Use to notify about all Groups with a non-negative result published from the beginning of time')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $recipients = $this->getEmailRecipients();
        if (empty($recipients)) {
            $this->outputDebug('No email recipients');
            return 0;
        }

        // Allow aborting if a no-send condition is met
        if ($reason = $this->getReasonToNotSend()) {
            $this->outputDebug($reason);
            return 0;
        }

        $subject = $this->getSubject();
        $html = $this->getHtmlEmailBody();
        $email = $this->emailBuilder->createHtml($recipients, $subject, $html);

        $this->outputDebugEmail($email);

        // Send the email
        if (!$input->getOption('do-not-send')) {
            $this->mailer->send($email);
        }

        // Log
        $this->logSentEmail($email);

        return 0;
    }

    abstract protected function getSubject(): string;
    abstract protected function getHtmlEmailBody(): string;
    abstract protected function logSentEmail(Email $email);

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
    }

    protected function outputDebug(string $line)
    {
        if ($this->output->isVerbose()) {
            $this->output->writeln($line);
        }
    }

    protected function outputDebugEmail(Email $email)
    {
        // Email stores From, Reply-To, To as arrays, convert to comma delimited
        $fromOutput = array_map(function(Address $A) { return $A->toString(); }, $email->getFrom());
        $replyToOutput = array_map(function(Address $A) { return $A->toString(); }, $email->getReplyTo());
        $toOutput = array_map(function(Address $A) { return $A->toString(); }, $email->getTo());

        $this->outputDebug('------');
        $this->outputDebug('From: ' . implode(', ', $fromOutput));
        $this->outputDebug('Reply-To: ' . implode(', ', $replyToOutput));
        $this->outputDebug('To: ' . implode(', ', $toOutput));
        $this->outputDebug('Subject: ' . $email->getSubject());
        $this->outputDebug('------');
        $this->outputDebug($email->getHtmlBody());
    }

    protected function getReasonToNotSend(): ?string
    {
        // Override to customize
        return null;
    }

    /**
     * Get Address objects for users who should be emailed this report.
     *
     * @return Address[]
     */
    protected function getEmailRecipients(): array
    {
        $users = $this->em->getRepository(AppUser::class)->findAll();

        /** @var AppUser[] $notifyUsers */
        $notifyUsers = array_filter($users, function(AppUser $u) {
            // Users without email address can't be notified
            if (!$u->getEmail()) {
                return false;
            }

            // Users with OLD assigned permission TODO: Remove via CVDLS-158
            if ($u->hasRoleExplicit(self::NOTIFY_USERS_WITH_ROLE_OLD)) {
                return true;
            }

            // Users assigned a permission on their Edit User page
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
}

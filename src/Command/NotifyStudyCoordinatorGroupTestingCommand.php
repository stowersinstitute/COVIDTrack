<?php

namespace App\Command;

use App\Entity\AppUser;
use App\Ldap\AppLdapUser;
use App\Ldap\AppLdapUserSynchronizer;
use App\Security\OptionalLdapUserProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Ldap\Security\LdapUser;
use Symfony\Component\Ldap\Security\LdapUserProvider;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Notifies Users that should be notified of a Participant Group having a
 * recommend testing conclusion from testing results.
 */
class NotifyStudyCoordinatorGroupTestingCommand extends Command
{
    /**
     * Users who explicitly have this role will be notified.
     */
    const NOTIFY_USERS_WITH_ROLE = 'ROLE_NOTIFY_GROUP_RECOMMENDED_TESTING';

    protected static $defaultName = 'app:notify:study-coordinator-group-testing';

    private $hierarchy;
    /** @var EntityManagerInterface */
    private $em;

    /** @var RouterInterface */
    private $router;

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;

    public function __construct(EntityManagerInterface $em, RouterInterface $router, RoleHierarchyInterface $hierarchy)
    {
        parent::__construct();

        $this->em = $em;
        $this->router = $router;
        $this->hierarchy = $hierarchy;
    }

    protected function configure()
    {
        $this
            ->setDescription('Notifies users that a Participant Group is recommended for further testing based on results.')
            ->addOption('additional-email', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'If testing required, this email will also be notified. Supports multiple uses.', [])
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
    }

    private function output(string $line)
    {
        $this->output->writeln($line);
    }

    private function outputDebug(string $line)
    {
//        if ($this->output->isVerbose()) {
            $this->output->writeln($line);
//        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $emails = array_merge(
            $this->getUserEmails(),
            $input->getOption('additional-email')
        );

        if (empty($emails)) {
            $this->outputDebug('No users to email');
            return 0;
        }

        $validEmails = array_filter($emails, function(string $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->outputDebug('Cannot notify invalid email: ' . $email);
                return false;
            }

            return true;
        });

        // TODO: Real email code
        foreach ($validEmails as $email) {
            $this->output('Will email: ' . $email);
        }

        return 0;
    }

    protected function userMustConfirm(InputInterface $input, OutputInterface $output, string $prompt)
    {
        $questionText = sprintf('%s (y/n)', $prompt);

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion($questionText, false);

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('User canceled');
            exit(1);
        }
    }

    /**
     * Get emails of users who should be emailed based on user permissions.
     *
     * @return string[]
     */
    private function getUserEmails(): array
    {
        $users = $this->em->getRepository(AppUser::class)->findAll();
        $notifyUsers = array_filter($users, function(AppUser $u) {
            // Users without email address can't be notified
            if (!$u->getEmail()) {
                return false;
            }

            return $u->hasRoleExplicit(self::NOTIFY_USERS_WITH_ROLE);
        });

        return array_filter($notifyUsers, function(AppUser $u) {
            return $u->getEmail();
        });
    }
}

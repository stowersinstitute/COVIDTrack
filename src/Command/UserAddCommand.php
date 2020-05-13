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

class UserAddCommand extends Command
{
    protected static $defaultName = 'app:user:add';

    /** @var Ldap  */
    private $ldap;

    /** @var LdapUserProvider */
    private $ldapUserProvider;

    /** @var AppLdapUserSynchronizer */
    private $ldapUserSynchronizer;

    /** @var EntityManagerInterface */
    private $em;

    public function __construct(
        EntityManagerInterface $em,
        Ldap $ldap,
        OptionalLdapUserProvider $ldapUserProvider,
        AppLdapUserSynchronizer $ldapUserSynchronizer
    ) {
        parent::__construct();

        $this->em = $em;
        $this->ldap = $ldap;
        $this->ldapUserProvider = $ldapUserProvider;
        $this->ldapUserSynchronizer = $ldapUserSynchronizer;
    }

    protected function configure()
    {
        $this
            ->setDescription('Adds a user to the system')
            ->addArgument('username', InputArgument::REQUIRED, 'username to add')
            ->addOption('as-sys-admin', null, InputOption::VALUE_NONE, 'If present, user will be added with admin privileges')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // todo: non-ldap support https://jira.stowers.org/browse/CVDLS-70
        $extraRoles = [];

        /** @var LdapUser $rawLdapUser */
        $rawLdapUser = $this->ldapUserProvider->loadUserByUsername($input->getArgument('username'));

        if (!$rawLdapUser) throw new \InvalidArgumentException(sprintf('"%s" was not found', $input->getArgument('username')));

        $appLdapUser = AppLdapUser::fromLdapUser($rawLdapUser);

        $output->writeln(sprintf("Username    : %s", $appLdapUser->getUsername()));
        $output->writeln(sprintf("Display Name: %s", $appLdapUser->getDisplayName()));
        $output->writeln(sprintf("Email       : %s", $appLdapUser->getEmailAddress()));
        $output->writeln(sprintf("Title       : %s", $appLdapUser->getTitle()));
        $output->writeln('');
        if ($input->getOption('as-sys-admin')) {
            $output->writeln('<comment>User will be a system administrator!</comment>');
            $output->writeln('');
            $extraRoles[] = 'ROLE_ADMIN';
        }

        $this->userMustConfirm($input, $output);

        $newUser = $this->addUserFromLdap($appLdapUser, $extraRoles);

        $output->writeln(sprintf(
            '<info>Added %s with uid %s</info>',
            $appLdapUser->getUsername(),
            $newUser->getId()
        ));
    }

    protected function addUserFromLdap(AppLdapUser $appLdapUser, array $extraRoles = []) : AppUser
    {
        $localUser = $this->ldapUserSynchronizer->createLocalUser($appLdapUser->getUsername());

        foreach ($extraRoles as $role) {
            $localUser->addRole($role);
        }

        $this->em->persist($localUser);
        $this->em->flush();

        return $localUser;
    }

    protected function userMustConfirm(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('Add this user? (y/n) ', false);

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('User canceled');
            exit(1);
        }
    }
}
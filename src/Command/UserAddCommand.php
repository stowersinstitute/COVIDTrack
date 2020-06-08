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

class UserAddCommand extends Command
{
    protected static $defaultName = 'app:user:add';

    /** @var UserPasswordEncoderInterface */
    private $passwordEncoder;

    /** @var RouterInterface */
    private $router;

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
        UserPasswordEncoderInterface $passwordEncoder,
        RouterInterface $router,
        Ldap $ldap,
        OptionalLdapUserProvider $ldapUserProvider,
        AppLdapUserSynchronizer $ldapUserSynchronizer
    ) {
        parent::__construct();

        $this->em = $em;

        $this->passwordEncoder = $passwordEncoder;
        $this->router = $router;

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
        $username = $input->getArgument('username');
        $extraRoles = [];

        $this->usernameMustNotExist($input, $output, $username);

        /** @var LdapUser $rawLdapUser */
        $rawLdapUser = null;
        try {
            $rawLdapUser = $this->ldapUserProvider->loadUserByUsername($username);
        } catch (UsernameNotFoundException $e) {
            // this is OK, let them create a local user
        }

        // LDAP user not found, add a local one
        if (!$rawLdapUser) {
            $this->userMustConfirm($input, $output, sprintf(
                '"%s" was not found in LDAP, add as a local user?',
                $username
            ));

            $this->addLocalUser($input, $output, $username);
            return 0;
        }

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

        return 0;
    }

    protected function addLocalUser(InputInterface $input, OutputInterface $output, string $username)
    {
        $extraRoles = [];
        $password = substr(md5(random_bytes(16)), 0, 12);

        $output->writeln('');
        $output->writeln('New User Details');
        $output->writeln('----------------');
        $output->writeln(sprintf("Username    : %s", $username));
        $output->writeln(sprintf("Password    : %s", $password));
        $output->writeln('');

        if ($input->getOption('as-sys-admin')) {
            $output->writeln('<comment>User will be a system administrator!</comment>');
            $output->writeln('');
            $extraRoles[] = 'ROLE_ADMIN';
        }

        $this->userMustConfirm($input, $output, 'Create this user?');

        $user = new AppUser($username);
        $user->setPassword($this->passwordEncoder->encodePassword(
            $user,
            $password
        ));

        foreach ($extraRoles as $role) {
            $user->addRole($role);
        }

        $this->em->persist($user);
        $this->em->flush();

        $userEditRoute = $this->router->generate('user_edit', ['username' => $username], Router::ABSOLUTE_URL);
        $output->writeln(sprintf('User created: %s', $userEditRoute));
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

    protected function usernameMustNotExist(InputInterface $input, OutputInterface $output, string $username)
    {
        $user = $this->em->getRepository(AppUser::class)
            ->findOneBy(['username' => $username]);

        if ($user) {
            throw new \InvalidArgumentException('User already exists');
        }
    }

    protected function userMustConfirm(InputInterface $input, OutputInterface $output, string $prompt = null)
    {
        if ($prompt === null) $prompt = 'Continue?';
        $questionText = sprintf('%s (y/n)', $prompt);

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion($questionText, false);

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('User canceled');
            exit(1);
        }
    }
}
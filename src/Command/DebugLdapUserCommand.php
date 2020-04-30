<?php


namespace App\Command;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Ldap\Security\LdapUser;
use Symfony\Component\Ldap\Security\LdapUserProvider;

class DebugLdapUserCommand extends Command
{
    protected static $defaultName = 'debug:app:ldap-user';

    /** @var Ldap  */
    private $ldap;

    /** @var LdapUserProvider */
    private $ldapUserProvider;

    public function __construct(
        Ldap $ldap,
        LdapUserProvider $ldapUserProvider
    ) {
        parent::__construct();

        $this->ldap = $ldap;
        $this->ldapUserProvider = $ldapUserProvider;
    }

    protected function configure()
    {
        $this
            ->setDescription('Prints out debugging information about an LDAP user')
            ->addArgument('username', InputArgument::REQUIRED, 'username to get information about')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var LdapUser $user */
        $user = $this->ldapUserProvider->loadUserByUsername($input->getArgument('username'));

        $ldapEntry = $user->getEntry();

        $output->writeln($ldapEntry->getDn());
        $output->writeln('');

        $output->writeln('Attributes');
        $table = new Table($output);
        $table->setHeaders(['Attribute', 'Value']);

        foreach ($ldapEntry->getAttributes() as $attrName => $attrValueArray) {
            $table->addRow([
                $attrName,
                $this->formatAttributeValueForOutput($attrValueArray)
            ]);
        }

        $table->render();
        return 0;
    }

    protected function formatAttributeValueForOutput($valueArray)
    {
        $prnValues = [];

        foreach ($valueArray as $value) {
            // Quick check for invalid values since we rely on strlen below
            if (!is_string($value)) return '[binary data]';

            // Test for binary data and don't echo it directly
            for ($i=0; $i < strlen($value); $i++) {
                $byteVal = ord($value[$i]);
                if ($byteVal < ord(' ') || $byteVal > ord('~')) {
                    return '[binary data]';
                }
            }

            $prnValues[] = $value;
        }

        return join("\n", $prnValues);
    }
}
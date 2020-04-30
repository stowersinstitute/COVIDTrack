<?php


namespace App\Security;


use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Ldap\Security\LdapUserProvider;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

/**
 * Overrides the default LdapUserProvider to skip lookup attempts if no LDAP_HOST is defined
 *
 * This is done so that LDAP can be enabled/disabled without modifying config files
 */
class OptionalLdapUserProvider extends LdapUserProvider
{
    public function __construct(LdapInterface $ldap, string $baseDn, string $searchDn = null, string $searchPassword = null, array $defaultRoles = [], string $uidKey = null, string $filter = null, string $passwordAttribute = null, array $extraFields = [])
    {
        $defaultRoles = [ 'ROLE_USER' ];

        parent::__construct($ldap, $baseDn, $searchDn, $searchPassword, $defaultRoles, $uidKey, $filter, $passwordAttribute, $extraFields);
    }

    public function loadUserByUsername($username)
    {
        if (!isset($_ENV['LDAP_HOST'])) {
            throw new UsernameNotFoundException('LDAP cannot be queried (LDAP_HOST is not defined)');
        }

        return parent::loadUserByUsername($username);
    }
}
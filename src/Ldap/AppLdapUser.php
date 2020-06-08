<?php


namespace App\Ldap;


use Symfony\Component\Ldap\Security\LdapUser;

/**
 * References
 *  - Standard LDAP fields: https://docs.oracle.com/cd/B14099_19/idmanage.1012/b15883/schema_attrs001.htm
 */
class AppLdapUser extends LdapUser
{
    public static function fromLdapUser(LdapUser $source)
    {
        return new AppLdapUser(
            $source->getEntry(),
            $source->getUsername(),
            '',
            $source->getRoles(),
            $source->getExtraFields()
        );
    }

    public function getDisplayName()
    {
        return $this->readFirstStringValue($this->getEntry()->getAttribute('displayName'));
    }

    public function getTitle()
    {
        return $this->readFirstStringValue($this->getEntry()->getAttribute('title'));
    }

    public function getEmailAddress()
    {
        return $this->readFirstStringValue($this->getEntry()->getAttribute('mail'));
    }

    protected function readFirstStringValue($ldapValueArray) : string
    {
        if (!$ldapValueArray) return '';

        return strval($ldapValueArray[0]);
    }
}
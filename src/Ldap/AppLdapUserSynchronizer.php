<?php


namespace App\Ldap;


use App\Entity\AppUser;
use App\Security\OptionalLdapUserProvider;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;

class AppLdapUserSynchronizer
{
    /** @var EntityManager  */
    protected $em;

    /** @var OptionalLdapUserProvider */
    protected $ldapUserProvider;

    public function __construct(
        EntityManagerInterface $em,
        OptionalLdapUserProvider $ldapUserProvider
    ) {
        $this->em = $em;
        $this->ldapUserProvider = $ldapUserProvider;
    }

    /**
     * Updates $localUser to be current with data stored in LDAP
     *
     * Note that you must flush the entity manager after calling this method
     */
    public function synchronize(AppUser $localUser) : AppUser
    {
        $ldapUser = $this->getAppLdapUser($localUser->getUsername());

        $this->updateLocalUserFromLdapUser($localUser, $ldapUser);

        return $localUser;
    }

    public function createLocalUser(string $username) : AppUser
    {
        $ldapUser = $this->getAppLdapUser($username);

        $localUser = new AppUser($ldapUser->getUsername());
        $localUser->setIsLdapUser(true);

        $this->updateLocalUserFromLdapUser($localUser, $ldapUser);

        return $localUser;
    }

    protected function updateLocalUserFromLdapUser(AppUser $localUser, AppLdapUser $ldapUser)
    {
        $localUser->setDisplayName($ldapUser->getDisplayName());
        $localUser->setEmail($ldapUser->getEmailAddress());
        $localUser->setTitle($ldapUser->getTitle());
    }

    protected function getAppLdapUser(string $username) : AppLdapUser
    {
        $ldapUser = $this->ldapUserProvider->loadUserByUsername($username);

        if (!$ldapUser) throw new \InvalidArgumentException('No ldap user found for ' . $username);

        return AppLdapUser::fromLdapUser($ldapUser);
    }
}
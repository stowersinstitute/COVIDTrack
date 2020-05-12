<?php


namespace App\Ldap;


use App\Entity\AppUser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;

class AppLdapUserSynchronizer
{
    /** @var EntityManager  */
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Ensures that a local user account exists for $ldapUser and it is current
     * with the data in $ldapUser
     *
     * Note that you must flush the entity manager after calling this method
     */
    public function synchronize(AppLdapUser $ldapUser) : AppUser
    {
        $localUser = $this->findLocalUser($ldapUser);
        if (!$localUser) {
            $localUser = $this->createLocalUser($ldapUser);
        }

        $this->updateLocalUserFromLdapUser($localUser, $ldapUser);

        return $localUser;
    }

    protected function createLocalUser(AppLdapUser $ldapUser) : AppUser
    {
        $localUser = $this->findLocalUser($ldapUser);

        // If a local user exists make sure it's flagged as an LDAP user
        if ($localUser) {
            $localUser->setIsLdapUser(true);
            return $localUser;
        }

        // No local user account exists, create one
        $localUser = new AppUser($ldapUser->getUsername());
        $localUser->setIsLdapUser(true);
        $localUser->setPassword('SEE_LDAP');

        $this->em->persist($localUser);

        return $localUser;
    }

    protected function updateLocalUserFromLdapUser(AppUser $localUser, AppLdapUser $ldapUser)
    {
        $localUser->setDisplayName($ldapUser->getDisplayName());
        $localUser->setEmail($ldapUser->getEmailAddress());
        $localUser->setTitle($ldapUser->getTitle());
    }

    protected function findLocalUser(AppLdapUser $ldapUser) : ?AppUser
    {
        return $this->em
            ->getRepository(AppUser::class)
            ->findOneBy(['username' => $ldapUser->getUsername()]);
    }
}
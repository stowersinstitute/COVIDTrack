<?php


namespace App\Security;


use App\Entity\AppUser;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\EntityUserProvider;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class LocalUserProvider extends EntityUserProvider
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppUser::class, 'username');
    }

    public function loadUserByUsername($username)
    {
        /** @var AppUser $user */
        $user = parent::loadUserByUsername($username);

        // LDAP users will appear in the local database but this user provider cannot return them
        // because otherwise Symfony will try to authenticate against their local data
        // This will fail because we don't store their password locally
        // Throwing an exception here will let the chain provider try the next user provider, which will be LDAP
        if ($user->isLdapUser()) {
            throw new UsernameNotFoundException('User is managed by LDAP');
        }

        return $user;
    }

}
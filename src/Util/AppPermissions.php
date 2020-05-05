<?php


namespace App\Util;


use App\Entity\AppUser;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;

class AppPermissions
{
    /**
     * Roles that a user could potentially be assigned
     *
     * See also:
     *  - security.yaml for the hierarchy
     * @var string[]
     */
    public const AVAILABLE_ROLES = [
        'ROLE_ADMIN',
        'ROLE_PARTICIPANT_GROUP_EDIT',
        'ROLE_PARTICIPANT_GROUP_VIEW'
    ];

    /**
     * Returns true if $user has $role via inheritance or directly
     */
    public static function userHasInheritedRole(AccessDecisionManagerInterface $adm, AppUser $user, $role) : bool
    {
        // Build a fake token to represent the user
        $token = new UsernamePasswordToken($user->getUsername(), '', 'main', $user->getRoles());

        return $adm->decide($token, [ $role ]);
    }

    /**
     * Returns true if $user has $role because they were directly granted it (as opposed to inheriting it through
     * the role hierarchy)
     */
    public static function userHasExplicitRole(AppUser $user, $role) : bool
    {
        return in_array($role, $user->getRoles());
    }
}
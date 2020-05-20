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
     *  - UserType.php for the user edit form to assign permissions
     *  - user/user-table.html.twig for the table that displays permissions for all users
     *
     * Search for DEPENDS_ON_AVAILABLE_ROLES
     *
     * @var string[]
     */
    public const AVAILABLE_ROLES = [
        'ROLE_ADMIN',

        // Kiosk
        'ROLE_KIOSK_UI',                // Can access the kiosk ui and drop off samples

        // Notifications
        'ROLE_NOTIFY_GROUP_RECOMMENDED_TESTING',

        // Participant Groups
        'ROLE_PARTICIPANT_GROUP_EDIT',
        'ROLE_PARTICIPANT_GROUP_VIEW',

        // Printing
        'ROLE_PRINT_TUBE_LABELS',
        'ROLE_PRINT_GROUP_LABELS',

        // Results
        'ROLE_RESULTS_EDIT',
        'ROLE_RESULTS_VIEW',

        // Specimens
        'ROLE_SPECIMEN_DROP_OFF',

        // Tubes
        'ROLE_TUBE_CHECK_IN',
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
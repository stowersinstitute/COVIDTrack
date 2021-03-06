<?php


namespace App\Form;


use App\Entity\AppUser;
use App\Util\AppPermissions;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;

class UserType extends AbstractType
{
    /** @var AccessDecisionManagerInterface */
    protected $accessDecisionManager;

    /**
     * If known, the user being edited. This is used to determine which permissions they have so the
     * permission checkboxes can be set correctly
     *
     * @var AppUser
     */
    protected $user;

    public function __construct(AccessDecisionManagerInterface $accessDecisionManager = null)
    {
        $this->accessDecisionManager = $accessDecisionManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['data'] instanceof AppUser) {
            $this->user = $options['data'];
        }
        else {
            throw new \InvalidArgumentException('Form data must be an AppUser');
        }

        $ldapLockedFieldHelp = '';
        if ($this->user->isLdapUser()) {
            $ldapLockedFieldHelp = 'This field cannot be changed for LDAP users';
        }

        $builder
            ->add('username', TextType::class, [
                'disabled' => $this->user->isLdapUser(),
                'help' => $ldapLockedFieldHelp,
            ])
            ->add('displayName', TextType::class, [
                'required' => false,
                'disabled' => $this->user->isLdapUser(),
                'help' => $ldapLockedFieldHelp,
            ])
            ->add('email', EmailType::class, [
                'required' => false,
                'disabled' => $this->user->isLdapUser(),
                'help' => $ldapLockedFieldHelp,
            ])
        ;

        // Search DEPENDS_ON_AVAILABLE_ROLES for other places needing updates when changing this list
        // Permissions
        $this->addRoleField($builder, 'ROLE_ADMIN', 'System Admin');

        $this->addRoleField($builder, 'ROLE_CONFIG_ALL', 'Configuration Access');

        $this->addRoleField($builder, 'ROLE_KIOSK_UI', 'Kiosk Access');

        $this->addRoleField($builder, 'ROLE_PARTICIPANT_GROUP_EDIT', 'Participant Groups: Edit');
        $this->addRoleField($builder, 'ROLE_PARTICIPANT_GROUP_VIEW', 'Participant Groups: View');

        $this->addRoleField($builder, 'ROLE_TUBE_CHECK_IN', 'Tube: Check In');

        $this->addRoleField($builder, 'ROLE_RESULTS_EDIT', 'Results: Upload and Edit');
        $this->addRoleField($builder, 'ROLE_RESULTS_VIEW', 'Results: View');

        $this->addRoleField($builder, 'ROLE_WELL_PLATE_EDIT', 'Well Plates: Edit');
        $this->addRoleField($builder, 'ROLE_WELL_PLATE_VIEW', 'Well Plates: View');

        $this->addRoleField($builder, 'ROLE_PRINT_TUBE_LABELS', 'Print: Tube Labels');
        $this->addRoleField($builder, 'ROLE_PRINT_GROUP_LABELS', 'Print: Group Labels');

        $this->addRoleField($builder, 'ROLE_NOTIFY_ABOUT_VIRAL_RESULTS', 'Notifications: Group Testing Recommended');
        $this->addRoleField($builder, 'ROLE_NOTIFY_ABOUT_ANTIBODY_RESULTS', 'Notifications: Antibody Testing Results');

        $builder
            ->add('Save', SubmitType::class, [
                'label' => 'Save',
                'attr' => ['class' => 'btn-primary'],
            ])
            ->getForm();
    }

    protected function addRoleField(FormBuilderInterface $builder, $role, $label)
    {
        $fieldName = sprintf('hasRole_%s', $role);
        $hasRole = $this->hasRole($role);
        $roleIsNotExplicit = $this->userDoesNotHaveExplicitRole($role);

        $help = null;
        $disabled = false;

        // If they have the role but it wasn't granted explicitly, do not let them remove the permission
        // and make it clear why they cannot
        if ($hasRole && $roleIsNotExplicit) {
            $disabled = true;
            $help = 'This ability is granted by another permission and cannot be removed.';
        }

        $builder->add($fieldName, CheckboxType::class, [
            'mapped' => false,          // does not directly map to an entity field
            'required' => false,        // if this is true the box must be checked
            'label' => $label,
            'data' => $hasRole,         // Check the box if they have the role
            'disabled' => $disabled,
            'help' => $help,
        ]);
    }

    protected function userDoesNotHaveExplicitRole($role)
    {
        if (!$this->accessDecisionManager) {
            throw new \LogicException('Attempted to call hasRole with no accessDecisionManager defined. Autowiring failed?');
        }
        if (!$this->user) {
            throw new \InvalidArgumentException('Cannot call this method without a user set.');
        }

        return !AppPermissions::userHasExplicitRole($this->user, $role);
    }

    protected function hasExplicitRole($role)
    {
        if (!$this->user) {
            throw new \InvalidArgumentException('Cannot call this method without a user set.');
        }

        return AppPermissions::userHasExplicitRole($this->user, $role);
    }

    protected function hasRole($role)
    {
        if (!$this->accessDecisionManager) {
            throw new \LogicException('Attempted to call hasRole with no accessDecisionManager defined. Autowiring failed?');
        }
        if (!$this->user) {
            throw new \InvalidArgumentException('Cannot call this method without a user set.');
        }

        return AppPermissions::userHasInheritedRole($this->accessDecisionManager, $this->user, $role);
    }
}
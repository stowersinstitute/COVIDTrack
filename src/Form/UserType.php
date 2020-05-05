<?php


namespace App\Form;


use App\Entity\AppUser;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class UserType extends AbstractType
{
    /** @var AuthorizationCheckerInterface */
    protected $authorizationChecker;

    /**
     * If known, the user being edited. This is used to determine which permissions they have so the
     * permission checkboxes can be set correctly
     *
     * @var AppUser
     */
    protected $user;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker = null)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['data'] instanceof AppUser) {
            $this->user = $options['data'];
        }
        else {
            throw new \InvalidArgumentException('Form data must be an AppUser');
        }

        $builder
            ->add('username', TextType::class)
            // Permissions
            ->add('hasRoleSysAdmin', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Is System Administrator',
                'data' => $this->hasRole('ROLE_ADMIN'),
            ])
            ->add('hasRoleParticipantGroupEdit', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Participant Groups: Edit and View',
                'data' => $this->hasRole('ROLE_PARTICIPANT_GROUP_EDIT'),
            ])
            ->add('Save', SubmitType::class)
            ->getForm();
    }

    protected function hasRole($role)
    {
        if (!$this->authorizationChecker) {
            throw new \LogicException('Attempted to call hasRole with no authorizationChecker defined. Autowiring failed?');
        }
        if (!$this->user) {
            throw new \InvalidArgumentException('Cannot check for roles without a user set.');
        }

        return $this->authorizationChecker->isGranted($role, $this->user);
    }
}
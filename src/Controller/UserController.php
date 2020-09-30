<?php


namespace App\Controller;


use App\Entity\AppUser;
use App\Entity\AuditLog;
use App\Form\UserType;
use App\Util\AppPermissions;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @Route(path="/users")
 */
class UserController extends AbstractController
{
    /** @var AccessDecisionManagerInterface */
    private $accessDecisionManager;

    public function __construct(AccessDecisionManagerInterface $accessDecisionManager)
    {
        $this->accessDecisionManager = $accessDecisionManager;
    }

    /**
     * @Route("/", name="user_list")
     */
    public function list()
    {
        $this->denyAccessUnlessPermissions();

        $users = $this->getDoctrine()
            ->getManager()
            ->getRepository(AppUser::class)
            ->findBy([], [
                'displayName' => 'ASC',
                'username' => 'ASC',
            ]);

        return $this->render(
            'user/list.html.twig',
            [
                'users' => $users,
                'rolesByUser' => $this->getRolesByUserArray($users),
            ]
        );
    }

    /**
     * Optional GET parameters:
     *  - forceLocal If present, automatic redirection to the LdapUserController will be disabled
     *
     * @Route(path="/new", methods={"GET", "POST"}, name="user_new")
     */
    public function new(Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->denyAccessUnlessPermissions();

        // If LDAP is enabled redirect to the LDAP workflow
        if (self::isLdapEnabled() && !$request->query->has('forceLocal')) {
            return $this->redirectToRoute('ldap_user_onboarding_start');
        }

        $form = $this->createFormBuilder()
            ->add('username', TextType::class, [
                'constraints' => [
                    new Callback(function($fieldValue, ExecutionContextInterface $context) {
                        $conflictingUser = $this->findUser($fieldValue);
                        if (!$conflictingUser) return;

                        // There is a conflicting user, build error message
                        $context->buildViolation('Username already exists')
                            ->atPath('username')
                            ->addViolation();
                    })
                ]
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'The password fields must match.',
                'options' => ['attr' => ['class' => 'password-field']],
                'required' => true,
                'first_options'  => ['label' => 'Password'],
                'second_options' => ['label' => 'Verify Password'],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Create user',
                'attr' => ['class' => 'btn-primary'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $newUser = $this->createLocalUser($passwordEncoder, $data['username'], $data['password']);

            return $this->redirectToRoute('user_edit', [
                'username' => $newUser->getUsername(),
            ]);
        }

        return $this->render('user/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    protected function createLocalUser(UserPasswordEncoderInterface $passwordEncoder, string $username, string $password) : AppUser
    {
        $em = $this->getDoctrine()->getManager();

        $user = new AppUser($username);
        $user->setPassword($passwordEncoder->encodePassword(
            $user,
            $password
        ));

        $em->persist($user);
        $em->flush();

        return $user;
    }


    /**
     * @Route("/{username}/edit", methods={"GET", "POST"}, name="user_edit")
     */
    public function edit(string $username, Request $request)
    {
        $this->denyAccessUnlessPermissions();

        $user = $this->mustFindUser($username);

        $auditLogs = $this->getDoctrine()
            ->getRepository(AuditLog::class)
            ->getLogEntries($user);

        /** @var UserType|FormInterface $form */
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Update permissions since these are not form fields directly applied to the user
            $this->syncPermissions($form, $user);

            $em = $this->getDoctrine()->getManager();
            $em->flush();

            // Send back to the "edit" page to confirm changes
            return $this->redirectToRoute('user_edit', [
                'username' => $user->getUsername(),
            ]);
        }

        return $this->render('user/form.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
            'auditLogs' => $auditLogs,
        ]);
    }

    /**
     * @Route("/{username}/change-password", methods={"GET", "POST"}, name="user_change_password")
     */
    public function changePassword(string $username, Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->denyAccessUnlessPermissions();
        $user = $this->mustFindUser($username);
        if ($user->isLdapUser()) throw new \InvalidArgumentException('Cannot change password for LDAP users');

        $form = $this->createFormBuilder()
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'The password fields must match.',
                'options' => ['attr' => ['class' => 'password-field']],
                'required' => true,
                'first_options'  => ['label' => 'New Password'],
                'second_options' => ['label' => 'Verify Password'],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Change Password',
                'attr' => ['class' => 'btn-primary'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($passwordEncoder->encodePassword(
                $user,
                $form->get('password')->getData()
            ));

            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('user_edit', [
                'username' => $user->getUsername(),
            ]);
        }

        return $this->render('user/change-password.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);

    }

    /**
     * Updates $user with the permissions defined in $form
     */
    protected function syncPermissions(FormInterface $form, AppUser $user)
    {
        $newUserRoles = [
            // All users should at least have this role
            'ROLE_USER',
        ];

        $permissionsFieldKey = 'hasRole_';
        foreach ($form->all() as $field) {
            $fieldName = $field->getName();

            // Skip fields that don't start with 'hasRole_'
            if (substr($fieldName, 0, strlen($permissionsFieldKey)) != $permissionsFieldKey) continue;

            // Skip fields that are not checked
            $formField = $form->get($fieldName);
            if (!$formField->getData() || $formField->isDisabled()) continue;

            $roleStr = substr($fieldName, strlen($permissionsFieldKey));
            $newUserRoles[] = $roleStr;
        }

        $user->setRoles($newUserRoles);
    }

    protected function denyAccessUnlessPermissions()
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN', 'Access Denied', 'You must be a system administrator to access this page');
    }

    /**
     * The output of this is an array that looks like:
     *
     *  [
     *    'ctadmin' => [ 'ROLE_ADMIN', 'ROLE_PARTICIPANT_GROUP_EDIT', '...' ],
     *    'tech' => ['ROLE_TECHNICIAN'],
     *  ]
     *
     * @param AppUser[] $users
     */
    protected function getRolesByUserArray(array $users)
    {
        $rolesByUser = [];
        foreach ($users as $user) {
            $inheritedUserRoles = [];
            foreach (AppPermissions::AVAILABLE_ROLES as $roleStr) {
                if (AppPermissions::userHasInheritedRole($this->accessDecisionManager, $user, $roleStr)) {
                    $inheritedUserRoles[] = $roleStr;
                }
            }

            $rolesByUser[$user->getUsername()] = $inheritedUserRoles;
        }

        return $rolesByUser;
    }

    protected function mustFindUser($username) : AppUser
    {
        $user = $this->findUser($username);

        if (!$user) throw new \InvalidArgumentException(sprintf('User "%s" not found', $username));

        return $user;
    }

    protected function findUser($username) : ?AppUser
    {
        return $this->getDoctrine()
            ->getManager()
            ->getRepository(AppUser::class)
            ->findOneByUsername($username);
    }

    private static function isLdapEnabled(): bool
    {
        return isset($_ENV['LDAP_HOST']) && $_ENV['LDAP_HOST'] != '';
    }
}
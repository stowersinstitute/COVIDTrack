<?php


namespace App\Controller;


use App\Entity\AppUser;
use App\Form\UserType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;

/**
 * @Route(path="/users")
 */
class UserController extends AbstractController
{
    /** @var AccessDecisionManagerInterface */
    private $accessDecisionManager;

    /**
     * Roles that a user could potentially be assigned
     *
     * See also:
     *  - security.yaml for the hierarchy
     * @var string[]
     */
    const AVAILABLE_ROLES = [
        'ROLE_ADMIN',
        'ROLE_PARTICIPANT_GROUP_EDIT',
        'ROLE_PARTICIPANT_GROUP_VIEW'
    ];

    public function __construct(AccessDecisionManagerInterface $accessDecisionManager)
    {
        $this->accessDecisionManager = $accessDecisionManager;
    }

    /**
     * @Route("/", name="user_list")
     */
    public function list()
    {
        $this->denyAcessUnlessPermissions();

        $users = $this->getDoctrine()
            ->getManager()
            ->getRepository(AppUser::class)
            ->findAll();

        dump($this->getRolesByUserArray($users));

        // Couldn't find a way to check if a user has an inherited role from within twig, so exporting
        // it as a map here



        return $this->render(
            'user/list.html.twig',
            [
                'users' => $users,
                'rolesByUser' => $this->getRolesByUserArray($users),
            ]
        );
    }

    /**
     * @Route(path="/new", methods={"GET", "POST"}, name="user_new")
     */
    public function new()
    {
        $this->denyAcessUnlessPermissions();

        return $this->render(
            'user/list.html.twig'
        );
    }

    /**
     * @Route("/{username}/edit", methods={"GET", "POST"}, name="user_edit")
     */
    public function edit(string $username, Request $request)
    {
        $this->denyAcessUnlessPermissions();

        $user = $this->mustFindUser($username);

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
        ]);
    }

    /**
     * Updates $user with the permissions defined in $form
     */
    protected function syncPermissions(FormInterface $form, AppUser $user)
    {
        $newUserRoles = [];

        if ($form->get('hasRoleSysAdmin')->getData()) {
            $newUserRoles[] = 'ROLE_ADMIN';
        }
        if ($form->get('hasRoleParticipantGroupEdit')->getData()) {
            $newUserRoles[] = 'ROLE_PARTICIPANT_GROUP_EDIT';
        }

        $user->setRoles($newUserRoles);
    }

    protected function denyAcessUnlessPermissions()
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN', 'Access Denied', 'You must be a system administrator to access this page');
    }

    protected function userHasInheritedRole(AppUser $user, $role)
    {
        $token = new UsernamePasswordToken($user->getUsername(), '', 'main', $user->getRoles());

        //$adm = $this->get('security.access.decision_manager');
        $adm = $this->accessDecisionManager;

        return $adm->decide($token, [ $role ]);
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
            foreach (self::AVAILABLE_ROLES as $roleStr) {
                if ($this->userHasInheritedRole($user, $roleStr)) $inheritedUserRoles[] = $roleStr;
            }

            $rolesByUser[$user->getUsername()] = $inheritedUserRoles;
        }

        return $rolesByUser;
    }

    protected function mustFindUser($username) : AppUser
    {
        $user = $this->getDoctrine()
            ->getManager()
            ->getRepository(AppUser::class)
            ->findOneByUsername($username);

        if (!$user) throw new \InvalidArgumentException(sprintf('User "%s" not found', $username));

        return $user;
    }

}
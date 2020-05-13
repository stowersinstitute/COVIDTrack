<?php


namespace App\Controller;


use App\Ldap\AppLdapUser;
use App\Ldap\AppLdapUserSynchronizer;
use App\Security\OptionalLdapUserProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Ldap\Security\LdapUserProvider;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @Route(path="/ldap-users")
 */
class LdapUserController extends AbstractController
{
    /** @var LdapUserProvider */
    private $ldapUserProvider;

    public function __construct(OptionalLdapUserProvider $ldapUserProvider)
    {
        $this->ldapUserProvider = $ldapUserProvider;
    }

    /**
     * @Route("/onboarding-start", name="ldap_user_onboarding_start")
     */
    public function onboardingStart(Request $request)
    {
        $this->denyAcessUnlessPermissions();

        $form = $this->createFormBuilder()
            ->add('username', TextType::class, [
                'constraints' => [
                    new Callback(function($fieldValue, ExecutionContextInterface $context) {
                        $ldapUser = $this->findLdapUser($fieldValue);
                        // LDAP user exists, can continue
                        if ($ldapUser) return;

                        // Not a valid user
                        $context->buildViolation('User not found in LDAP')
                            ->atPath('username')
                            ->addViolation();
                    })
                ]
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Next',
                'attr' => ['class' => 'btn-primary'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $ldapUser = $this->findLdapUser($form->get('username')->getData());

            return $this->redirectToRoute('ldap_user_onboarding_confirm', [
                'username' => $ldapUser->getUsername(),
            ]);
        }

        return $this->render('ldap-user/onboarding-start.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/onboarding-start/{username}", name="ldap_user_onboarding_confirm")
     */
    public function onboardingConfirm(string $username)
    {
        $this->denyAcessUnlessPermissions();

        $ldapUser = $this->findLdapUser($username);

        return $this->render('ldap-user/onboarding-confirm.html.twig', [
            'ldapUser' => $ldapUser,
        ]);
    }

    /**
     * @Route("/onboarding-commit/{username}", methods={"POST"}, name="ldap_user_onboarding_commit")
     */
    public function onboardingDo(string $username, AppLdapUserSynchronizer $ldapUserSynchronizer)
    {
        $this->denyAcessUnlessPermissions();

        $localUser = $ldapUserSynchronizer->createLocalUser($username);

        $this->getDoctrine()->getManager()->persist($localUser);
        $this->getDoctrine()->getManager()->flush();

        // After the user is created the normal user management pages can be used
        return $this->redirectToRoute('user_edit', [
            'username' => $localUser->getUsername(),
        ]);
    }

    protected function denyAcessUnlessPermissions()
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN', 'Access Denied', 'You must be a system administrator to access this page');
    }

    protected function findLdapUser(string $username) : ?AppLdapUser
    {
        $ldapUser = $this->ldapUserProvider->loadUserByUsername($username);
        if (!$ldapUser) return null;

        return AppLdapUser::fromLdapUser($ldapUser);
    }
}
<?php

namespace App\Security;

use App\Entity\AppUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Ldap\Security\LdapUser;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Guard\Authenticator\AbstractFormLoginAuthenticator;
use Symfony\Component\Security\Guard\PasswordAuthenticatedInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginFormAuthenticator extends AbstractFormLoginAuthenticator implements PasswordAuthenticatedInterface
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    private $entityManager;
    private $urlGenerator;
    private $csrfTokenManager;
    private $passwordEncoder;

    /** @var Ldap  */
    private $ldap;

    /**
     * String representing how to look up a user to authenticate them
     *
     * For example, "{username}@CONTOSO.COM" would be replaced by jdoe@CONTOSO.COM before attempting to bind()
     *
     * @var string
     */
    private $ldapAuthUserDnFormat = '';

    public function __construct(
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $urlGenerator,
        CsrfTokenManagerInterface $csrfTokenManager,
        UserPasswordEncoderInterface $passwordEncoder,
        Ldap $ldap,
        $ldapAuthUserDnFormat = ''
    ) {
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->passwordEncoder = $passwordEncoder;
        $this->ldap = $ldap;
        $this->ldapAuthUserDnFormat = $ldapAuthUserDnFormat;
    }

    public function supports(Request $request)
    {
        return self::LOGIN_ROUTE === $request->attributes->get('_route')
            && $request->isMethod('POST');
    }

    public function getCredentials(Request $request)
    {
        $credentials = [
            'username' => $request->request->get('username'),
            'password' => $request->request->get('password'),
            'csrf_token' => $request->request->get('_csrf_token'),
        ];
        $request->getSession()->set(
            Security::LAST_USERNAME,
            $credentials['username']
        );

        return $credentials;
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $token = new CsrfToken('authenticate', $credentials['csrf_token']);
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw new InvalidCsrfTokenException();
        }

        $user = $userProvider->loadUserByUsername($credentials['username']);

        if (!$user) {
            // fail authentication with a custom error
            throw new CustomUserMessageAuthenticationException('Username could not be found.');
        }

        return $user;
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        // Authenticate against LDAP for LdapUsers
        if ($user instanceof LdapUser) {
            return $this->isLdapPasswordValid($credentials, $user);
        }

        // Fall back to local password authentication
        // Note that LDAP users will not have a password stored in the local database
        return $this->passwordEncoder->isPasswordValid($user, $credentials['password']);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function getPassword($credentials): ?string
    {
        return $credentials['password'];
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        // When an LdapUser logs in sync their record in the local database
        $authenticatedUser = $token->getUser();
        if ($authenticatedUser instanceof LdapUser) {
            $this->syncLocalUserFromLdapUser($authenticatedUser);
        }

        // Redirect to where they were going or the home page
        if ($targetPath = $this->getTargetPath($request->getSession(), $providerKey)) {
            return new RedirectResponse($targetPath);
        }

        // NOTE: Duplicated in SecurityController::login
        return new RedirectResponse($this->urlGenerator->generate('app_default_index'));
    }

    protected function getLoginUrl()
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }

    protected function isLdapPasswordValid($credentials, LdapUser $user)
    {
        $username = $this->ldap->escape($credentials['username'], '', LdapInterface::ESCAPE_DN);

        // LDAP password is valid if we can bind as the user
        // See LdapBindAuthenicationProvider for the official Symfony implementation
        $dn = str_replace('{username}', $username, $this->ldapAuthUserDnFormat);

        try {
            // Throws an exception if password is invalid
            $this->ldap->bind($dn, $credentials['password']);
        } catch (ConnectionException $e) {
            return false;
        }

        return true;
    }

    protected function syncLocalUserFromLdapUser(LdapUser $ldapUser)
    {
        $localUser = $this->entityManager
            ->getRepository(AppUser::class)
            ->findOneBy(['username' => $ldapUser->getUsername()]);

        if (!$localUser) {
            $localUser = new AppUser($ldapUser->getUsername());

            $localUser->setIsLdapUser(true);
            $localUser->setRoles($ldapUser->getRoles());

            $this->entityManager->persist($localUser);
        }

        // Add additional field syncs here

        $this->entityManager->flush();
    }
}

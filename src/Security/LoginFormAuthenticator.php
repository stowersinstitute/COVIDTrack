<?php

namespace App\Security;

use App\Entity\AppUser;
use App\Ldap\AppLdapUser;
use App\Ldap\AppLdapUserSynchronizer;
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

    /** @var AppLdapUserSynchronizer */
    private $ldapUserSynchronizer;

    public function __construct(
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $urlGenerator,
        CsrfTokenManagerInterface $csrfTokenManager,
        UserPasswordEncoderInterface $passwordEncoder,
        Ldap $ldap,
        AppLdapUserSynchronizer $ldapUserSynchronizer,
        $ldapAuthUserDnFormat = ''
    ) {
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->passwordEncoder = $passwordEncoder;
        $this->ldap = $ldap;
        $this->ldapUserSynchronizer = $ldapUserSynchronizer;
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
        // The object type here is determined by the user provider. It may be an AppUser or an LdapUser
        $authenticatedUser = $token->getUser();
        // We always want to work with the local AppUser
        $localUser = null;

        if ($authenticatedUser instanceof LdapUser) {
            $localUser = $this->ldapUserSynchronizer->synchronize(AppLdapUser::fromLdapUser($authenticatedUser));
        }
        if ($authenticatedUser instanceof AppUser) {
            $localUser = $authenticatedUser;
        }

        if (!$localUser) throw new \InvalidArgumentException('Unexpected user type ' . get_class($authenticatedUser));

        // Update login tracking fields
        $localUser->setHasLoggedIn(true);
        $localUser->setLastLoggedInAt(new \DateTimeImmutable());

        // Commit any changes from the sync and updating last login details
        $this->entityManager->flush();

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
}

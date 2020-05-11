<?php

namespace App\Entity;

use App\Util\AuditLogUtils;
use App\Util\StringUtils;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity(repositoryClass="App\Repository\AppUserRepository")
 *
 * @Gedmo\Loggable(logEntryClass="App\Entity\AuditLog")
 */
class AppUser implements UserInterface
{
    use TimestampableEntity;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=180, unique=true, nullable=false)
     *
     * @Gedmo\Versioned
     */
    private $username;

    /**
     * @ORM\Column(type="json")
     *
     * @Gedmo\Versioned
     */
    private $roles = [];

    /**
     * @var string The hashed password (will be null if this user came from LDAP)
     * @ORM\Column(type="string", nullable=true)
     */
    private $password;

    /**
     * @var bool If true, this user is managed by LDAP and should be authenticated against it
     * @ORM\Column(type="boolean", name="isLdapUser")
     *
     * @Gedmo\Versioned
     */
    private $isLdapUser = false;

    /**
     * @var boolean Whether the user has ever logged in
     *
     * @ORM\Column(name="hasLoggedIn", type="boolean", nullable=false)
     */
    protected $hasLoggedIn;

    /**
     * @var \DateTimeImmutable The first time the user logged in
     *
     * @ORM\Column(name="firstLoggedInAt", type="datetime_immutable", nullable=true)
     */
    protected $firstLoggedInAt;

    /**
     * @var \DateTimeImmutable The most recent time the user logged in
     *
     * @ORM\Column(name="lastLoggedInAt", type="datetime_immutable", nullable=true)
     */
    protected $lastLoggedInAt;

    /**
     * @var string User's preferred display name
     *
     * @ORM\Column(name="displayName", type="string", length=255, nullable=true)
     *
     * @Gedmo\Versioned
     */
    protected $displayName;

    /**
     * @var string Email address for contacting this user
     *
     * @ORM\Column(name="email", type="string", length=255, nullable=true)
     *
     * @Gedmo\Versioned
     */
    protected $email;

    /**
     * @var string User's title within the organization
     *
     * @ORM\Column(name="title", type="string", length=255, nullable=true)
     *
     * @Gedmo\Versioned
     */
    protected $title;

    public function __construct(string $username)
    {
        $this->hasLoggedIn = false;
        $this->username = $username;

        // All users should at least have ROLE_USER
        $this->roles = ['ROLE_USER'];
    }

    /**
     * Convert audit log field changes from internal format to human-readable format.
     *
     * The input to this method will be a map of properties and their raw values
     *
     *     [
     *         "status" => "IN_PROCESS", // STATUS_IN_PROCESS constant value
     *         "createdAt" => \DateTime(...),
     *     ]
     *
     * This method should convert the changes to human-readable values like this:
     *
     *     [
     *         "Status" => "In Process",
     *         "Created At" => AuditLogUtils::getHumanReadableString($value)
     *     ]
     *
     * @param array $changes Keys are internal entity propertyNames, Values are internal entity values
     * @return mixed[] Keys are human-readable field names, Values are human-readable strings
     */
    public static function makeHumanReadableAuditLogFieldChanges(array $changes): array
    {
        $return = [];

        foreach ($changes as $fieldId => $value) {
            $hrProperty = StringUtils::camelCaseToTitleCase($fieldId);
            $hrValue = AuditLogUtils::getHumanReadableString($value);

            $return[$hrProperty] = $hrValue;
        }

        return $return;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUsername(): string
    {
        return (string) $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function addRole(string $role): self
    {
        $this->roles[] = $role;
        $this->roles = array_unique($this->roles);

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getPassword(): string
    {
        return (string) $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getSalt()
    {
        // not needed when using the "bcrypt" algorithm in security.yaml
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function isLdapUser(): bool
    {
        return $this->isLdapUser;
    }

    public function setIsLdapUser(bool $isLdapUser): void
    {
        $this->isLdapUser = $isLdapUser;
    }

    public function isHasLoggedIn(): bool
    {
        return $this->hasLoggedIn;
    }

    public function setHasLoggedIn(bool $hasLoggedIn): void
    {
        // Update firstLoggedInAt if this is the first time they've logged in
        if ($hasLoggedIn && $this->hasLoggedIn === false && $this->firstLoggedInAt === null) {
            $this->firstLoggedInAt = new \DateTimeImmutable();
        }

        $this->hasLoggedIn = $hasLoggedIn;
    }

    public function getFirstLoggedInAt(): ?\DateTimeImmutable
    {
        return $this->firstLoggedInAt;
    }

    public function setFirstLoggedInAt(?\DateTimeImmutable $firstLoggedInAt): void
    {
        $this->firstLoggedInAt = $firstLoggedInAt;
    }

    public function getLastLoggedInAt(): ?\DateTimeImmutable
    {
        return $this->lastLoggedInAt;
    }

    public function setLastLoggedInAt(?\DateTimeImmutable $lastLoggedInAt): void
    {
        $this->lastLoggedInAt = $lastLoggedInAt;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): void
    {
        $this->displayName = $displayName;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }
}

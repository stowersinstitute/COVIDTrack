<?php

namespace App\DataFixtures;

use App\Entity\AppUser;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * For permissions information see security.yaml
 */
class AppUserFixtures extends Fixture
{
    private $passwordEncoder;

    public function __construct(UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    /**
     * Search for DEPENDS_ON_AVAILABLE_ROLES
     */
    public function load(ObjectManager $manager)
    {
        // System Administrator
        $this->buildUser($manager, 'ctadmin', ['ROLE_ADMIN']);

        // Study Coordinator
        $this->buildUser($manager, 'coordinator', ['ROLE_PARTICIPANT_GROUP_EDIT']);

        // Specimen check-in technician
        $this->buildUser($manager, 'checkintech', ['ROLE_SPECIMEN_CHECK_IN']);

        // Kiosk
        $this->buildUser($manager, 'kiosk', ['ROLE_KIOSK_UI']);

        $manager->flush();;
    }

    /**
     * Builds and persists a new AppUser
     *
     * If null, $password is set to $username
     */
    protected function buildUser($em, $username, $roles = [], $password = null)
    {
        if ($password === null) $password = $username;

        $user = new AppUser($username);

        if ($roles) {
            $user->setRoles($roles);
        }

        $user->setPassword($this->passwordEncoder->encodePassword(
            $user,
            $password
        ));

        $em->persist($user);
    }
}

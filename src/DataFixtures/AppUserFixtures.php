<?php

namespace App\DataFixtures;

use App\Entity\AppUser;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * For permissions information see security.yaml
 */
class AppUserFixtures extends Fixture implements FixtureGroupInterface
{
    private $passwordEncoder;

    public static function getGroups(): array
    {
        return ['users'];
    }

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

        // Study Coordinator (with explicit notification role)
        $this->buildUser($manager, 'coordinator', [
            'ROLE_PARTICIPANT_GROUP_EDIT',
            'ROLE_PRINT_GROUP_LABELS',
            'ROLE_NOTIFY_GROUP_RECOMMENDED_TESTING',
        ]);

        // Specimen Collection Team
        $this->buildUser($manager, 'samplecollection', ['ROLE_PRINT_TUBE_LABELS', 'ROLE_TUBE_CHECK_IN', 'ROLE_WELL_PLATE_EDIT']);

        // Kiosk
        $this->buildUser($manager, 'kiosk', ['ROLE_KIOSK_UI']);

        // Viral Testing Team
        $this->buildUser($manager, 'testingtech', ['ROLE_RESULTS_EDIT', 'ROLE_WELL_PLATE_VIEW']);

        // Viral Analysis Team
        $this->buildUser($manager, 'analysistech', ['ROLE_RESULTS_VIEW', 'ROLE_WELL_PLATE_VIEW']);

        // Media Prep
        $this->buildUser($manager, 'mediaprep', ['ROLE_PRINT_TUBE_LABELS', 'ROLE_PARTICIPANT_GROUP_SCHEDULE_VIEW']);

        $manager->flush();
    }

    /**
     * Builds and persists a new AppUser
     *
     * If null, $password is set to $username
     */
    protected function buildUser(ObjectManager $em, $username, $roles = [], $password = null)
    {
        if ($password === null) $password = $username;

        // This is to support appending to production data
        $exists = $em->getRepository(AppUser::class)
            ->findOneBy(['username' => $username]);
        if ($exists) return;

        $user = new AppUser($username);
        $user->setEmail(sprintf('%s@example.com', $username));

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

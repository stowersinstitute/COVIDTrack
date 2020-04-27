<?php

namespace App\DataFixtures;

use App\Entity\AppUser;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class AppUserFixtures extends Fixture
{
    private $passwordEncoder;

    public function __construct(UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    public function load(ObjectManager $manager)
    {
        // Administrator
        $adminUser = new AppUser('ctadmin');
        $adminUser->addRole('ROLE_SYSTEM_ADMINISTRATOR');
        $adminUser->setPassword($this->passwordEncoder->encodePassword(
            $adminUser,
            'ctadmin'
        ));
        $manager->persist($adminUser);

        // Technician
        $techUser = new AppUser('tech');
        $techUser->addRole('ROLE_TECHNICIAN');
        $techUser->setPassword($this->passwordEncoder->encodePassword(
            $techUser,
            'tech'
        ));
        $manager->persist($techUser);

        $manager->flush();;
    }

}

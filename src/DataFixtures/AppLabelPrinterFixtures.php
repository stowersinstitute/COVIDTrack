<?php

namespace App\DataFixtures;

use App\Entity\LabelPrinter;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class AppLabelPrinterFixtures extends Fixture
{
    private $passwordEncoder;

    public function __construct(UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    public function load(ObjectManager $manager)
    {
        $printer = new LabelPrinter();
        $printer->setTitle('Dummy Printer');
        $printer->setDpi('203');
        $printer->setDescription('Dummy Printer for Image Previewing');
        $printer->setHost('none');
        $printer->setMediaWidth(4);
        $printer->setMediaHeight(1);

        $manager->persist($printer);

        $manager->flush();
    }
}

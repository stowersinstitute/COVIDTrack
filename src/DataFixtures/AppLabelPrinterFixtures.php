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
        $printer->setTitle('ZPL Printer ZD420');
        $printer->setDpi('203');
        $printer->setDescription('Printer for previewing ZPL printing');
        $printer->setHost('none');
        $printer->setMediaWidthIn(4);
        $printer->setMediaHeightIn(1);

        $manager->persist($printer);

        $manager->flush();
    }
}

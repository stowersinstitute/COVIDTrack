<?php


namespace App\Controller;


use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * @Route(path="/debug")
 */
class DebugController extends AbstractController
{
    /**
     * @Route(path="/generic-error")
     */
    public function genericError()
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        throw new \ErrorException('A generic error message');
    }
}
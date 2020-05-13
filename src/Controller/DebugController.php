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
        throw new \ErrorException('A generic error message');
    }
}
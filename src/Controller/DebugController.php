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

    /**
     * @Route(path="/out-of-memory-error")
     */
    public function outOfMemory()
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $stuffs = [];
        while (true) $stuffs[] = "MORE THINGS";
    }

    /**
     * @Route(path="/runtime-error")
     */
    public function runtimeError()
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $object = null;

        $object->getSomethingThatDoesNotExist();
    }

    /**
     * @Route(path="/parse-error")
     */
    public function parseError()
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $object = null;

        eval('$object = null; $object->getSometh');
    }
}
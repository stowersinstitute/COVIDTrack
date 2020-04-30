<?php


namespace App\Controller;


use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * @Route(path="/")
 */
class DefaultController extends AbstractController
{
    /**
     * @Route(path="/", methods={"GET"})
     */
    public function index()
    {
        return $this->render('index.html.twig', [

        ]);
    }
}
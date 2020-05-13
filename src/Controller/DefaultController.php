<?php


namespace App\Controller;


use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use App\Entity\Tube;
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
        $em = $this->getDoctrine()->getManager();
        $groupRepo = $em->getRepository(ParticipantGroup::class);
        $specimenRepo = $em->getRepository(Specimen::class);
        $tubeRepo = $em->getRepository(Tube::class);

        return $this->render('index.html.twig', [
            'numActiveGroups' => $groupRepo->getActiveCount(),

            'numTubesReturned' => $tubeRepo->getReturnedCount(),

            'numSpecimensInProcess' => $specimenRepo->getInProcessCount(),
        ]);
    }
}
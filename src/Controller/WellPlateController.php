<?php


namespace App\Controller;


use App\Entity\WellPlate;
use App\Form\WellPlateType;
use Gedmo\Loggable\Entity\LogEntry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class WellPlateController
 * @package App\Controller
 *
 * @Route(path="/well-plates")
 */
class WellPlateController extends AbstractController
{

    /**
     * @Route(path="/", methods={"GET"})
     */
    public function list()
    {
        $wellPlates = $this->getDoctrine()->getRepository(WellPlate::class)->findAll();

        return $this->render('well-plate/well-plate-list.html.twig', [
            'headers' => ['ID', 'Title'],
            'wellPlates' => $wellPlates,
        ]);
    }

    /**
     * @Route(path="/new", methods={"GET", "POST"})
     */
    public function new(Request $request) : Response
    {
        $wellPlate = new WellPlate();

        $form = $this->createForm(WellPlateType::class, $wellPlate);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $wellPlate = $form->getData();

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($wellPlate);
            $entityManager->flush();

            return $this->redirectToRoute('app_wellplate_list');
        }

        return $this->render('well-plate/well-plate-form.html.twig', ['new' => true, 'form'=>$form->createView()]);
    }

    /**
     * @Route("/{id}", methods={"GET", "POST"})
     */
    public function update(int $id, Request $request) : Response
    {
        $wellPlate = $this->getDoctrine()->getRepository(WellPlate::class)->find($id);
        
        $form = $this->createForm(WellPlateType::class, $wellPlate);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->flush();

            return $this->redirectToRoute('app_wellplate_list');
        }

        $revisions = $this->getDoctrine()->getRepository(LogEntry::class)->getLogEntries($wellPlate);

        return $this->render('well-plate/well-plate-form.html.twig', [
            'new' => false,
            'form'=>$form->createView(),
            'wellPlate'=>$wellPlate,
            'revisions'=>$revisions
        ]);
    }

}
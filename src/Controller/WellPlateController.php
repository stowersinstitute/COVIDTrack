<?php

namespace App\Controller;

use App\Entity\AuditLog;
use App\Entity\WellPlate;
use App\Form\WellPlateType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Interact with Well Plates
 *
 * @Route(path="/well-plates")
 */
class WellPlateController extends AbstractController
{
    /**
     * @Route(path="/", methods={"GET"}, name="well_plate_list")
     */
    public function list()
    {
        $wellPlates = $this->getDoctrine()->getRepository(WellPlate::class)->findAll();

        return $this->render('well-plate/list.html.twig', [
            'wellPlates' => $wellPlates,
        ]);
    }

    /**
     * View a single Specimen.
     *
     * @Route("/{id<\d+>}", methods={"GET"}, name="well_plate_view")
     */
    public function view(string $id)
    {
        $wellPlate = $this->getDoctrine()->getRepository(WellPlate::class)->find($id);

        return $this->render('well-plate/view.html.twig', [
            'wellPlate' => $wellPlate,
        ]);
    }

    /**
     * @Route(path="/new", methods={"GET", "POST"})
     */
    public function new(Request $request) : Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $wellPlate = new WellPlate();

        $form = $this->createForm(WellPlateType::class, $wellPlate);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $wellPlate = $form->getData();

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($wellPlate);
            $entityManager->flush();

            return $this->redirectToRoute('well_plate_list');
        }

        return $this->render('well-plate/well-plate-form.html.twig', ['new' => true, 'form'=>$form->createView()]);
    }

    /**
     * @Route("/{id}", methods={"GET", "POST"})
     */
    public function update(int $id, Request $request) : Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $wellPlate = $this->getDoctrine()->getRepository(WellPlate::class)->find($id);
        
        $form = $this->createForm(WellPlateType::class, $wellPlate);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->flush();

            return $this->redirectToRoute('well_plate_list');
        }

        $revisions = $this->getDoctrine()->getRepository(AuditLog::class)->getLogEntries($wellPlate);

        return $this->render('well-plate/well-plate-form.html.twig', [
            'new' => false,
            'form'=>$form->createView(),
            'wellPlate'=>$wellPlate,
            'revisions'=>$revisions
        ]);
    }
}

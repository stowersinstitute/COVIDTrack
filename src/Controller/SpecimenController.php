<?php

namespace App\Controller;

use App\Entity\AuditLog;
use App\Entity\Specimen;
use App\Form\SpecimenForm;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Interact with Specimens.
 *
 * @Route(path="/specimens")
 */
class SpecimenController extends AbstractController
{
    /**
     * List all Specimens
     *
     * @Route(path="/", methods={"GET"})
     */
    public function list()
    {
        $this->denyAccessUnlessGranted('ROLE_SPECIMEN_VIEW');

        $specimens = $this->getDoctrine()
            ->getRepository(Specimen::class)
            ->findAll();

        return $this->render('specimen/specimen-list.html.twig', [
            'specimens' => $specimens,
        ]);
    }

    /**
     * View a single Specimen.
     *
     * @Route("/{accessionId<C[A-Z]{8}>}", methods={"GET", "POST"})
     */
    public function view(string $accessionId)
    {
        $this->denyAccessUnlessGranted('ROLE_SPECIMEN_VIEW');

        $specimen = $this->findSpecimen($accessionId);

        $auditLogs = $this->getDoctrine()
            ->getRepository(AuditLog::class)
            ->getLogEntries($specimen);

        return $this->render('specimen/specimen-view.html.twig', [
            'specimen' => $specimen,
            'auditLogs' => $auditLogs,
        ]);
    }

    /**
     * Create a single new Specimen
     *
     * @Route(path="/new", methods={"GET", "POST"})
     */
    public function new(Request $request) : Response
    {
        $this->denyAccessUnlessGranted('ROLE_SPECIMEN_EDIT');

        $form = $this->createForm(SpecimenForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Specimen $specimen */
            $specimen = $form->getData();

            $em = $this->getDoctrine()->getManager();
            $em->persist($specimen);
            $em->flush();

            return $this->redirectToRoute('app_specimen_view', [
                'accessionId' => $specimen->getAccessionId(),
            ]);
        }

        return $this->render('specimen/specimen-form.html.twig', [
            'new' => true,
            'form'=> $form->createView(),
        ]);
    }

    /**
     * Edit a single Specimen.
     *
     * @Route("/{accessionId<C[A-Z]{8}>}/edit", methods={"GET", "POST"})
     */
    public function edit(string $accessionId, Request $request) : Response
    {
        $this->denyAccessUnlessGranted('ROLE_SPECIMEN_EDIT');

        $specimen = $this->findSpecimen($accessionId);

        $form = $this->createForm(SpecimenForm::class, $specimen);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->flush();

            return $this->redirectToRoute('app_specimen_view', [
                'accessionId' => $specimen->getAccessionId(),
            ]);
        }

        return $this->render('specimen/specimen-form.html.twig', [
            'new' => false,
            'form' => $form->createView(),
            'specimen' => $specimen,
        ]);
    }

    private function findSpecimen($id): Specimen
    {
        $s = $this->getDoctrine()
            ->getRepository(Specimen::class)
            ->findOneByAnyId($id);

        if (!$s) {
            throw new \InvalidArgumentException('Cannot find Specimen');
        }

        return $s;
    }
}

<?php

namespace App\Controller;

use App\Entity\Specimen;
use App\Form\SpecimenForm;
use App\Form\SpecimenFormData;
use App\Form\SpecimenType;
use Gedmo\Loggable\Entity\LogEntry;
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
     * TODO: CVDLS-30 Replace requirements below with real accession ID prefix
     * @Route("/{accessionId}", methods={"GET", "POST"}, requirements={"accessionId"="CID\d+"})
     */
    public function view(string $accessionId)
    {
        $specimen = $this->findSpecimen($accessionId);

        $revisions = $this->getDoctrine()
            ->getRepository(LogEntry::class)
            ->getLogEntries($specimen);

        return $this->render('specimen/specimen-view.html.twig', [
            'specimen' => $specimen,
            'revisions' => $revisions,
        ]);
    }

    /**
     * Create a single new Specimen
     *
     * @Route(path="/new", methods={"GET", "POST"})
     */
    public function new(Request $request) : Response
    {
        $form = $this->createForm(SpecimenForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $accessionId = 'CID'.time(); // TODO: CVDLS-30 Replace with real accession ID prefix
            $s = Specimen::createFromForm($accessionId, $data);

            $em = $this->getDoctrine()->getManager();
            $em->persist($s);
            $em->flush();

            return $this->redirectToRoute('app_specimen_list');
        }

        return $this->render('specimen/specimen-form.html.twig', [
            'new' => true,
            'form'=> $form->createView(),
        ]);
    }

    /**
     * Edit a single Specimen.
     *
     * TODO: CVDLS-30 Replace requirements below with real accession ID prefix
     * @Route("/{accessionId}/edit", methods={"GET", "POST"}, requirements={"accessionId"="CID\d+"})
     */
    public function edit(string $accessionId, Request $request) : Response
    {
        $specimen = $this->findSpecimen($accessionId);

        $form = $this->createForm(SpecimenForm::class, $specimen->getUpdateFormData());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $d = $form->getData();
            $specimen->updateFromFormData($d);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->flush();

            return $this->redirectToRoute('app_specimen_list');
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

<?php

namespace App\Controller;

use App\Entity\AuditLog;
use App\Entity\Specimen;
use App\Entity\SpecimenResult;
use App\Entity\SpecimenResultQPCR;
use App\Form\SpecimenForm;
use App\Form\SpecimenResultQPCRForm;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Interact with Specimen Results for qPCR.
 *
 * @Route(path="/results/qpcr")
 */
class SpecimenResultQPCRController extends AbstractController
{
    /**
     * List all Results
     *
     * @Route(path="/", methods={"GET"}, name="app_results_qpcr_list")
     */
    public function list()
    {
        $results = $this->getDoctrine()
            ->getRepository(SpecimenResultQPCR::class)
            ->findAll();

        return $this->render('results/qpcr/list.html.twig', [
            'results' => $results,
        ]);
    }

    /**
     * Create a single new qPCR Result
     *
     * @Route(path="/new", methods={"GET", "POST"}, name="app_results_qpcr_new")
     */
    public function new(Request $request) : Response
    {
        $form = $this->createForm(SpecimenResultQPCRForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var SpecimenResultQPCR $result */
            $result = $form->getData();

            $em = $this->getDoctrine()->getManager();
            $em->persist($result);
            $em->flush();

            return $this->redirectToRoute('app_results_qpcr_list');
        }

        return $this->render('results/qpcr/form.html.twig', [
            'new' => true,
            'form'=> $form->createView(),
        ]);
    }

    /**
     * Edit a single qPCR Result.
     *
     * @Route("/{id<\d+>}/edit", methods={"GET", "POST"}, name="app_results_qpcr_edit")
     */
    public function edit(string $id, Request $request) : Response
    {
        $result = $this->findResult($id);

        $form = $this->createForm(SpecimenResultQPCRForm::class, $result);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->flush();

            return $this->redirectToRoute('app_results_qpcr_list');
        }

        return $this->render('results/qpcr/form.html.twig', [
            'new' => false,
            'form' => $form->createView(),
            'result' => $result,
        ]);
    }

    private function findResult($id): SpecimenResultQPCR
    {
        $q = $this->getDoctrine()
            ->getRepository(SpecimenResultQPCR::class)
            ->find($id);

        if (!$q) {
            throw new \InvalidArgumentException('Cannot find qPCR Result');
        }

        return $q;
    }
}

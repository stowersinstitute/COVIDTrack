<?php

namespace App\Controller;

use App\Entity\Specimen;
use App\Entity\SpecimenResultQPCR;
use App\Entity\SpecimenWell;
use App\Form\SpecimenResultQPCRFilterForm;
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
    public function list(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_RESULTS_VIEW');

        $formFilterData = [];
        $repo = $this->getDoctrine()->getRepository(SpecimenResultQPCR::class);

        $filterForm = $this->createForm(SpecimenResultQPCRFilterForm::class);
        $filterForm->handleRequest($request);

        if ($filterForm->isSubmitted() && $filterForm->isValid()) {
            $formFilterData = $filterForm->getData();
        }

        $results = $repo->filterByFormData($formFilterData);

        return $this->render('results/qpcr/list.html.twig', [
            'results' => $results,
            'filterForm' => $filterForm->createView(),
        ]);
    }

    /**
     * Create a single new Result
     *
     * Optional query string params:
     *
     * - accessionId (string) Specimen.accessionId to create results for
     *
     * @Route(path="/new", methods={"GET", "POST"}, name="app_results_qpcr_new")
     */
    public function new(Request $request) : Response
    {
        $this->denyAccessUnlessGranted('ROLE_RESULTS_EDIT');

        throw new \RuntimeException('This feature not currently supported');

        $result = null;

        // Query string params may indicate desired Specimen
        if ($request->query->has('accessionId')) {
            $specimen = $this->mustFindSpecimen($request->query->get('accessionId'));
            $result = new SpecimenResultQPCR($specimen);
        }

        $form = $this->createForm(SpecimenResultQPCRForm::class, $result);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var SpecimenResultQPCR $result */
            $result = $form->getData();

            $em = $this->getDoctrine()->getManager();
            $em->persist($result);
            $em->flush();

            if ($request->query->has('accessionId')) {
                return $this->redirectToRoute('app_specimen_view', [
                    'accessionId' => $request->query->get('accessionId'),
                ]);
            }

            return $this->redirectToRoute('app_results_qpcr_list');
        }

        return $this->render('results/qpcr/form.html.twig', [
            'new' => true,
            'form'=> $form->createView(),
        ]);
    }

    /**
     * Edit a single Result.
     *
     * Optional query string params:
     *
     * - accessionId (string) Redirect to this Specimen's page after edit is complete
     *
     * @Route("/{id<\d+>}/edit", methods={"GET", "POST"}, name="app_results_qpcr_edit")
     */
    public function edit(string $id, Request $request) : Response
    {
        $this->denyAccessUnlessGranted('ROLE_RESULTS_EDIT');

        $result = $this->findResult($id);

        $form = $this->createForm(SpecimenResultQPCRForm::class, $result);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->flush();

            // When given Specimen accessionId query string param,
            // redirect there after edit complete
            if ($request->query->has('accessionId')) {
                return $this->redirectToRoute('app_specimen_view', [
                    'accessionId' => $request->query->get('accessionId'),
                ]);
            }

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
            throw new \InvalidArgumentException('Cannot find Result');
        }

        return $q;
    }

    private function mustFindSpecimen(string $accessionId): Specimen
    {
        $s = $this->getDoctrine()
            ->getRepository(Specimen::class)
            ->findOneByAnyId($accessionId);

        if (!$s) {
            throw new \InvalidArgumentException('Cannot find Specimen');
        }

        return $s;
    }
}

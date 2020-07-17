<?php

namespace App\Controller;

use App\Entity\Specimen;
use App\Entity\SpecimenResultQPCR;
use App\Entity\WellPlate;
use App\Form\SpecimenResultQPCRFilterForm;
use App\Form\QPCRResultsForm;
use Doctrine\ORM\EntityManagerInterface;
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
     * @Route(path="/", methods={"GET"}, name="results_qpcr_list")
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
     * - specimenAccessionId (string) Specimen.accessionId to create results for
     *
     * @Route(path="/new/{specimenAccessionId}", methods={"GET", "POST"}, name="results_qpcr_new")
     */
    public function new(string $specimenAccessionId, Request $request, EntityManagerInterface $em) : Response
    {
        $this->denyAccessUnlessGranted('ROLE_RESULTS_EDIT');

        $specimen = $this->mustFindSpecimen($specimenAccessionId);

        $data = [];

        $form = $this->createForm(QPCRResultsForm::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();

            /** @var WellPlate $wellPlate */
            $wellPlate = $formData['wellPlate'];
            $position = $formData['position'];
            $conclusion = $formData['conclusion'];

            // Must be on selected Well Plate
            if (!$specimen->isOnWellPlate($wellPlate)) {
                throw new \InvalidArgumentException(sprintf('Specimen "%s" is not in a Well on Well Plate "%s"', $specimen->getAccessionId(), $wellPlate->getBarcode()));
            }

            // Well must be at given Position
            $well = $specimen->getWellAtPosition($wellPlate, $position);
            if (!$well) {
                throw new \InvalidArgumentException(sprintf('Specimen "%s" is not in Well "%s" on Well Plate "%s"', $specimen->getAccessionId(), $well->getPositionAlphanumeric(), $wellPlate->getBarcode()));
            }

            $result = new SpecimenResultQPCR($well, $conclusion);

            $em->persist($result);
            $em->flush();

            return $this->redirectToRoute('app_specimen_view', [
                'accessionId' => $specimen->getAccessionId(),
            ]);
        }

        return $this->render('results/qpcr/form.html.twig', [
            'new' => true,
            'form'=> $form->createView(),
            'specimenAccessionId' => $specimen->getAccessionId(),
        ]);
    }

    /**
     * Edit a single Result.
     *
     * Optional query string params:
     *
     * - accessionId (string) Redirect to this Specimen's page after edit is complete
     *
     * @Route("/{id<\d+>}/edit", methods={"GET", "POST"}, name="results_qpcr_edit")
     */
    public function edit(string $id, Request $request, EntityManagerInterface $em) : Response
    {
        $this->denyAccessUnlessGranted('ROLE_RESULTS_EDIT');

        $result = $this->findResult($id);
        $data = [
            'specimen' => $result->getSpecimen(),
            'wellPlate' => $result->getWellPlate(),
            'position' => $result->getWellPosition(),
            'conclusion' => $result->getConclusion(),
        ];

        $form = $this->createForm(QPCRResultsForm::class, $data, [
            'edit' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();

            $result->setConclusion($formData['conclusion']);

            $em->flush();

            // When given Specimen accessionId query string param,
            // redirect there after edit complete
            if ($request->query->has('accessionId')) {
                return $this->redirectToRoute('app_specimen_view', [
                    'accessionId' => $request->query->get('accessionId'),
                ]);
            }

            return $this->redirectToRoute('results_qpcr_list');
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

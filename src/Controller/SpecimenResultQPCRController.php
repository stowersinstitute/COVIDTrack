<?php

namespace App\Controller;

use App\Entity\AuditLog;
use App\Entity\Specimen;
use App\Entity\SpecimenResultQPCR;
use App\Form\SpecimenResultQPCRFilterForm;
use App\Form\QPCRResultsForm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Interact with Viral Results for qPCR performed on a Specimen.
 *
 * @Route(path="/results/qpcr")
 */
class SpecimenResultQPCRController extends AbstractController
{
    /**
     * List all Viral Results
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
     * View a single Viral Result.
     *
     * @Route("/{id<\d+>}/view", methods={"GET"}, name="results_qpcr_view")
     */
    public function view(string $id, EntityManagerInterface $em) : Response
    {
        $this->denyAccessUnlessGranted('ROLE_RESULTS_VIEW');

        $result = $this->findResult($id);

        $auditLogs = $this->getDoctrine()
            ->getRepository(AuditLog::class)
            ->getLogEntries($result);

        return $this->render('results/qpcr/view.html.twig', [
            'result' => $result,
            'auditLogs' => $auditLogs,
        ]);
    }

    /**
     * Create a single new Viral Result
     *
     * - specimenAccessionId (string) Specimen.accessionId to create results for
     *
     * @Route(path="/new/{specimenAccessionId}", methods={"GET", "POST"}, name="results_qpcr_new")
     */
    public function new(string $specimenAccessionId, Request $request, EntityManagerInterface $em) : Response
    {
        $this->denyAccessUnlessGranted('ROLE_RESULTS_EDIT');

        $specimen = $this->mustFindSpecimen($specimenAccessionId);

        $form = $this->createForm(QPCRResultsForm::class, null, [
            'specimen' => $specimen,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var SpecimenResultQPCR $result */
            $result = $form->getData();

            $em->persist($result);
            $em->flush();

            return $this->redirectToRoute('app_specimen_view', [
                'accessionId' => $specimen->getAccessionId(),
            ]);
        }

        return $this->render('results/qpcr/form.html.twig', [
            'new' => true,
            'form'=> $form->createView(),
            'specimen' => $specimen,
        ]);
    }

    /**
     * Edit a single Viral Result.
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

        $specimen = $result->getSpecimen();
        $form = $this->createForm(QPCRResultsForm::class, $result, [
            'editResult' => $result,
            'specimen' => $specimen,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            // When given Specimen accessionId query string param,
            // redirect there after edit complete
            if ($request->query->has('accessionId')) {
                return $this->redirectToRoute('app_specimen_view', [
                    'accessionId' => $request->query->get('accessionId'),
                ]);
            }

            return $this->redirectToRoute('results_qpcr_view', [
                'id' => $id,
            ]);
        }

        return $this->render('results/qpcr/form.html.twig', [
            'new' => false,
            'form' => $form->createView(),
            'result' => $result,
            'specimen' => $specimen,
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

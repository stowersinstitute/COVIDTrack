<?php

namespace App\Controller;

use App\Entity\Specimen;
use App\Entity\SpecimenResultAntibody;
use App\Form\AntibodyResultsForm;
use App\Form\SpecimenResultAntibodyFilterForm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Interact with Specimen Results for Antibodies.
 *
 * @Route(path="/results/antibody")
 */
class SpecimenResultAntibodyController extends AbstractController
{
    /**
     * List all Results
     *
     * @Route(path="/", methods={"GET"}, name="results_antibody_list")
     */
    public function list(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_RESULTS_VIEW');

        $formFilterData = [];
        $repo = $this->getDoctrine()->getRepository(SpecimenResultAntibody::class);

        $filterForm = $this->createForm(SpecimenResultAntibodyFilterForm::class);
        $filterForm->handleRequest($request);

        if ($filterForm->isSubmitted() && $filterForm->isValid()) {
            $formFilterData = $filterForm->getData();
        }

        $results = $repo->filterByFormData($formFilterData);

        return $this->render('results/antibody/list.html.twig', [
            'results' => $results,
            'filterForm' => $filterForm->createView(),
        ]);
    }

    /**
     * Create a single new Result
     *
     * - specimenAccessionId (string) Specimen.accessionId to create results for
     *
     * @Route(path="/new/{specimenAccessionId}", methods={"GET", "POST"}, name="results_antibody_new")
     */
    public function new(string $specimenAccessionId, Request $request, EntityManagerInterface $em) : Response
    {
        $this->denyAccessUnlessGranted('ROLE_RESULTS_EDIT');

        $specimen = $this->mustFindSpecimen($specimenAccessionId);

        $form = $this->createForm(AntibodyResultsForm::class, null, [
            'specimen' => $specimen,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var SpecimenResultAntibody $result */
            $result = $form->getData();

            $em->persist($result);
            $em->flush();

            return $this->redirectToRoute('app_specimen_view', [
                'accessionId' => $specimen->getAccessionId(),
            ]);
        }

        return $this->render('results/antibody/form.html.twig', [
            'new' => true,
            'form'=> $form->createView(),
            'specimen' => $specimen,
        ]);
    }

    /**
     * Edit a single Result.
     *
     * @Route("/{id<\d+>}/edit", methods={"GET", "POST"}, name="results_antibody_edit")
     */
    public function edit(string $id, Request $request, EntityManagerInterface $em) : Response
    {
        $this->denyAccessUnlessGranted('ROLE_RESULTS_EDIT');

        $result = $this->findResult($id);

        $specimen = $result->getSpecimen();
        $form = $this->createForm(AntibodyResultsForm::class, $result, [
            'editResult' => $result,
            'specimen' => $specimen,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('app_specimen_view', [
                'accessionId' => $specimen->getAccessionId(),
            ]);
        }

        return $this->render('results/antibody/form.html.twig', [
            'new' => false,
            'form' => $form->createView(),
            'result' => $result,
            'specimen' => $specimen,
        ]);
    }

    private function findResult($id): SpecimenResultAntibody
    {
        $r = $this->getDoctrine()
            ->getRepository(SpecimenResultAntibody::class)
            ->find($id);

        if (!$r) {
            throw new \InvalidArgumentException('Cannot find Result');
        }

        return $r;
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

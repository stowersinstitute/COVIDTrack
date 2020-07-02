<?php

namespace App\Controller;

use App\Entity\Specimen;
use App\Entity\SpecimenResultAntibody;
use App\Entity\SpecimenWell;
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
     * @Route(path="/", methods={"GET"}, name="app_results_antibody_list")
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
     * Optional query string params:
     *
     * - accessionId (string) Specimen.accessionId to create results for
     *
     * @Route(path="/new", methods={"GET", "POST"}, name="app_results_antibody_new")
     */
    public function new(Request $request, EntityManagerInterface $em) : Response
    {
        $this->denyAccessUnlessGranted('ROLE_RESULTS_EDIT');

        $data = [
            'specimen' => null,
        ];

        // Query string params may indicate desired Specimen
        if ($request->query->has('accessionId')) {
            $specimen = $this->mustFindSpecimen($request->query->get('accessionId'));
            $data['specimen'] = $specimen;
        }

        $form = $this->createForm(AntibodyResultsForm::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $specimen = $data['specimen'];
            $wellPlate = $data['wellPlate'];
            $position = $data['position'];
            $well = new SpecimenWell($wellPlate, $specimen, $position);
            $well->setWellIdentifier($data['wellIdentifier']);

            $signal = $data['conclusionQuantitative'];
            $result = new SpecimenResultAntibody($well, $signal);

            $em->persist($result);
            $em->flush();

            if ($request->query->has('accessionId')) {
                return $this->redirectToRoute('app_specimen_view', [
                    'accessionId' => $request->query->get('accessionId'),
                ]);
            }

            return $this->redirectToRoute('app_results_antibody_list');
        }

        return $this->render('results/antibody/form.html.twig', [
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
     * @Route("/{id<\d+>}/edit", methods={"GET", "POST"}, name="app_results_antibody_edit")
     */
    public function edit(string $id, Request $request, EntityManagerInterface $em) : Response
    {
        $this->denyAccessUnlessGranted('ROLE_RESULTS_EDIT');

        $result = $this->findResult($id);
        $data = [
            'specimen' => $result->getSpecimen(),
            'wellPlate' => $result->getWellPlate(),
            'position' => $result->getWellPosition(),
            'wellIdentifier' => $result->getWellIdentifier(),
            'conclusionQuantitative' => $result->getConclusionQuantitative(),
        ];

        $form = $this->createForm(AntibodyResultsForm::class, $data, [
            'edit' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();

            $result->setConclusionQuantitative($formData['conclusionQuantitative']);
            $result->setWellIdentifier($formData['wellIdentifier']);

            $em->flush();

            // When given Specimen accessionId query string param,
            // redirect there after edit complete
            if ($request->query->has('accessionId')) {
                return $this->redirectToRoute('app_specimen_view', [
                    'accessionId' => $request->query->get('accessionId'),
                ]);
            }

            return $this->redirectToRoute('app_results_antibody_list');
        }

        return $this->render('results/antibody/form.html.twig', [
            'new' => false,
            'form' => $form->createView(),
            'result' => $result,
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

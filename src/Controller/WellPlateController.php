<?php

namespace App\Controller;

use App\Entity\WellPlate;
use App\Form\WellPlateForm;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
        $this->mustHaveViewPermissions();

        $wellPlates = $this->getDoctrine()->getRepository(WellPlate::class)->findAll();

        return $this->render('well-plate/list.html.twig', [
            'wellPlates' => $wellPlates,
        ]);
    }

    /**
     * View a single Well Plate.
     *
     * @Route("/{barcode}", methods={"GET"}, name="well_plate_view")
     */
    public function view(string $barcode)
    {
        $this->mustHaveViewPermissions();

        $wellPlate = $this->getDoctrine()
            ->getRepository(WellPlate::class)
            ->findOneByBarcode($barcode);
        if (!$wellPlate) {
            throw new NotFoundHttpException('Cannot find Well Plate by barcode');
        }

        return $this->render('well-plate/view.html.twig', [
            'wellPlate' => $wellPlate,
        ]);
    }

    /**
     * Edit a single Well Plate.
     *
     * @Route("/edit/{barcode}", methods={"GET","POST"}, name="well_plate_edit")
     */
    public function edit(Request $request, string $barcode)
    {
        $this->mustHaveEditPermissions();

        $plate = $this->getDoctrine()
            ->getRepository(WellPlate::class)
            ->findOneByBarcode($barcode);
        if (!$plate) {
            throw new NotFoundHttpException('Cannot find Well Plate by barcode');
        }

        $form = $this->createForm(WellPlateForm::class, $plate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->flush();

            return $this->redirectToRoute('well_plate_view', [
                'barcode' => $plate->getBarcode(),
            ]);
        }

        return $this->render('well-plate/edit.html.twig', [
            'new' => false,
            'form' => $form->createView(),
            'wellPlate' => $plate,
        ]);
    }

    private function mustHaveViewPermissions()
    {
        $this->denyAccessUnlessGranted('ROLE_WELL_PLATE_VIEW');
    }

    private function mustHaveEditPermissions()
    {
        $this->denyAccessUnlessGranted('ROLE_WELL_PLATE_EDIT');
    }
}

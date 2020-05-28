<?php

namespace App\Controller;

use App\Entity\AuditLog;
use App\Entity\WellPlate;
use App\Form\WellPlateType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        $this->mustHavePermissions();

        $wellPlates = $this->getDoctrine()->getRepository(WellPlate::class)->findAll();

        return $this->render('well-plate/list.html.twig', [
            'wellPlates' => $wellPlates,
        ]);
    }

    /**
     * View a single Specimen.
     *
     * @Route("/{barcode}", methods={"GET"}, name="well_plate_view")
     */
    public function view(string $barcode)
    {
        $this->mustHavePermissions();

        $wellPlate = $this->getDoctrine()
            ->getRepository(WellPlate::class)
            ->findOneByAnyId($barcode);
        if (!$wellPlate) {
            throw new NotFoundHttpException('Cannot find Well Plate');
        }

        return $this->render('well-plate/view.html.twig', [
            'wellPlate' => $wellPlate,
        ]);
    }

    private function mustHavePermissions()
    {
        $this->denyAccessUnlessGranted('ROLE_WELL_PLATE_VIEW');
    }
}

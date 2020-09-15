<?php

namespace App\Controller;

use App\Entity\Tube;
use App\ExcelImport\TubeCheckinSalivaImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Actions for the Tube check-in queue. These tubes have been returned by
 * Participants and allow Technicians to acknowledge receipt.
 *
 * @Route(path="/checkin")
 */
class TubeCheckinQueueController extends AbstractController
{
    /**
     * List Tubes that have been returned at a kiosk.
     *
     * @Route(path="/queue", methods={"GET"}, name="checkin_queue")
     */
    public function queue()
    {
        $this->denyAccessUnlessGranted('ROLE_TUBE_CHECK_IN');

        $tubes = $this->getDoctrine()
            ->getRepository(Tube::class)
            ->findReadyForCheckin();

        $typeCounts = array_reduce($tubes, function(array $carry, Tube $T) {
            $txt = $T->getTypeText();
            if (!isset($carry[$txt])) {
                $carry[$txt] = 0;
            }

            $carry[$txt]++;

            return $carry;
        }, []);
        ksort($typeCounts);

        return $this->render('checkin/queue.html.twig', [
            'tubes' => $tubes,
            'typeCounts' => $typeCounts,
            'typeCountsTotal' => array_sum($typeCounts),
        ]);
    }
    /**
     * Process list of Tubes sent for External Processing.
     *
     * Required POST params:
     *
     * - tubeAccessionIds (string[]) Each Tube.accessionId to mark for External Processing
     *
     * @Route(path="/send-for-external-processing", methods={"POST"}, name="checkin_queue_external_processing")
     */
    public function sendForExternalProcessing(Request $request, EntityManagerInterface $em)
    {
        $this->denyAccessUnlessGranted('ROLE_TUBE_CHECK_IN');

        $errors = [];

        // Request POST params
        $accessionIds = $request->request->get('tubeAccessionIds');
        if (count($accessionIds) < 1) {
            $errors[] = 'No selected tubeAccessionIds';
        }

        // Locate Tubes
        if (!$errors) {
            $tubes = $em
                ->getRepository(Tube::class)
                ->findBy([
                    'accessionId' => $accessionIds,
                ]);

            // Verify locating all queried-for Tubes
            if (count($accessionIds) !== count($tubes)) {
                $errors[] = 'Cannot find all selected Tubes';
            }

            // Verify Tube workflow permits this operation
            foreach ($tubes as $tube) {
                if (!$tube->willAllowExternalProcessing()) {
                    $errors[] = sprintf('Tube "%s" does not currently support External Processing', $tube->getAccessionId());
                }
            }
        }

        // Process and Save
        if (!$errors) {
            foreach ($tubes as $tube) {
                $tube->markExternalProcessing();
            }

            $em->flush();
        }

        return $this->render('checkin/mark-for-external-processing.html.twig', [
            'tubes' => $tubes,
            'errors' => $errors,
            'typeCounts' => [],
            'typeCountsTotal' => 0,
        ]);
    }

    /**
     * Reject a single Tube.
     *
     * Required POST params:
     *
     * - tubeId {string} Tube.accessionId
     *
     * @Route(path="/reject", methods={"POST"}, name="checkin_reject_tube")
     */
    public function reject(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_TUBE_CHECK_IN');

        $tubeId = $request->request->get('tubeId');

        /** @var Tube $tube */
        $tube = $this->getDoctrine()
            ->getRepository(Tube::class)
            ->findOneByAccessionId($tubeId);
        if (!$tube) {
            $msg = 'Cannot find Tube by ID';
            return $this->createJsonErrorResponse($msg);
        }

        $tube->markRejected($this->getUser()->getUsername());

        $em = $this->getDoctrine()->getManager();
        $em->persist($tube);
        $em->flush();

        return new JsonResponse([
            'tubeId' => $tubeId,
        ]);
    }

    private function createJsonErrorResponse(string $msg): JsonResponse
    {
        return new JsonResponse([
            'errorMsg' => $msg,
        ], 400);
    }
}

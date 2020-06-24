<?php

namespace App\Controller;

use App\Entity\Tube;
use App\ExcelImport\TubeCheckinSalivaImporter;
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
     * List Specimens that have been returned, ready for check-in
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
     * Decide on check-in status for a single Tube.
     *
     * Required POST params:
     *
     * - tubeId {string} Tube.accessionId
     * - decision {string} ACCEPTED or REJECTED
     *
     * @Route(path="/decide", methods={"POST"}, name="checkin_decide_tube")
     */
    public function decide(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_TUBE_CHECK_IN');

        // Tube
        $tubeId = $request->request->get('tubeId');
        /** @var Tube $tube */
        $tube = $this->getDoctrine()
            ->getRepository(Tube::class)
            ->findOneByAccessionId($tubeId);
        if (!$tube) {
            $msg = 'Cannot find Tube by ID';
            return $this->createJsonErrorResponse($msg);
        }

        // Decision
        $validDecisions = [
            TubeCheckinSalivaImporter::STATUS_ACCEPTED,
            TubeCheckinSalivaImporter::STATUS_REJECTED,
        ];
        $decision = $request->request->get('decision');
        if (!in_array($decision, $validDecisions)) {
            $msg = 'Invalid "decision" parameter. Must be one of: ' . implode(', ', $validDecisions);
            return $this->createJsonErrorResponse($msg);
        }
        switch ($decision) {
            case TubeCheckinSalivaImporter::STATUS_ACCEPTED:
                $tube->markAccepted($this->getUser()->getUsername());
                break;
            case TubeCheckinSalivaImporter::STATUS_REJECTED:
                $tube->markRejected($this->getUser()->getUsername());
                break;
        }

        $em = $this->getDoctrine()->getManager();
        $em->persist($tube);
        $em->flush();

        return new JsonResponse([
            'tubeId' => $tubeId,
            'status' => $decision,
        ]);
    }

    private function createJsonErrorResponse(string $msg): JsonResponse
    {
        return new JsonResponse([
            'errorMsg' => $msg,
        ], 400);
    }
}

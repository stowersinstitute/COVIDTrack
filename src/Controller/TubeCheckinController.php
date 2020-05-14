<?php

namespace App\Controller;

use App\Entity\ExcelImportWorkbook;
use App\Entity\Tube;
use App\ExcelImport\ExcelImporter;
use App\ExcelImport\SpecimenCheckinImporter;
use App\Form\GenericExcelImportType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Actions for the Tube check-in process. These tubes have been returned by
 * Participants and allow Technicians to acknowledge receipt.
 *
 * @Route(path="/checkin")
 */
class TubeCheckinController extends AbstractController
{
    /**
     * List Specimens that have been returned, ready for check-in
     *
     * @Route(path="/queue", methods={"GET"}, name="checkin_queue")
     */
    public function queue()
    {
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

        return $this->render('checkin/list.html.twig', [
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
     * - decision {string} APPROVED or REJECTED
     *
     * @Route(path="/decide", methods={"POST"}, name="checkin_decide_tube")
     */
    public function decide(Request $request)
    {
        // Tube
        $tubeId = $request->request->get('tubeId');
        /** @var Tube $tube */
        $tube = $this->getDoctrine()
            ->getRepository(Tube::class)
            ->findOneByAnyId($tubeId);
        if (!$tube) {
            $msg = 'Cannot find Tube by ID';
            return $this->createJsonErrorResponse($msg);
        }

        // Decision
        $validDecisions = [
            'APPROVED',
            'REJECTED',
        ];
        $decision = $request->request->get('decision');
        if (!in_array($decision, $validDecisions)) {
            $msg = 'Invalid "decision" parameter. Must be one of: ' . implode(', ', $validDecisions);
            return $this->createJsonErrorResponse($msg);
        }
        switch ($decision) {
            case 'APPROVED':
                $tube->markAccepted($this->getUser()->getUsername());
                break;
            case 'REJECTED':
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

    /**
     * @Route(path="/import/start", name="checkin_import_start")
     */
    public function importStart(Request $request, ExcelImporter $excelImporter)
    {
        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(GenericExcelImportType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $excelFile */
            $excelFile = $form->get('excelFile')->getData();

            $workbook = $excelImporter->createWorkbookFromUpload($excelFile);
            $em->persist($workbook);
            $em->flush();

            return $this->redirectToRoute('checkin_import_preview', [
                'importId' => $workbook->getId(),
            ]);
        }

        return $this->render('excel-import/base-excel-import-start.twig', [
            'itemLabel' => 'Approve/Reject',
            'importForm' => $form->createView(),
        ]);
    }

    /**
     * @Route("/import/preview/{importId<\d+>}", name="checkin_import_preview")
     */
    public function importPreview(int $importId, ExcelImporter $excelImporter)
    {
        $importingWorkbook = $this->mustFindImport($importId);
        $excelImporter->userMustHavePermissions($importingWorkbook);

        $importer = new SpecimenCheckinImporter(
            $this->getDoctrine()->getManager(),
            $importingWorkbook->getFirstWorksheet()
        );

        $output = $importer->process();

        return $this->render('checkin/excel-import-preview.html.twig', [
            'importId' => $importId,
            'importer' => $importer,
            'rejected' => $output['rejected'] ?? [],
            'accepted' => $output['accepted'] ?? [],
            'importPreviewTemplate' => 'checkin/excel-import-table.html.twig',
            'importCommitRoute' => 'checkin_import_commit',
            'importCommitText' => 'Save Check-ins',
        ]);
    }

    /**
     * @Route("/import/commit/{importId<\d+>}", methods={"POST"}, name="checkin_import_commit")
     */
    public function importCommit(int $importId, ExcelImporter $excelImporter)
    {
        $em = $this->getDoctrine()->getManager();

        $importingWorkbook = $this->mustFindImport($importId);
        $excelImporter->userMustHavePermissions($importingWorkbook);

        $importer = new SpecimenCheckinImporter(
            $em,
            $importingWorkbook->getFirstWorksheet()
        );
        $output = $importer->process(true);

        // Clean up workbook from the database
        $em->remove($importingWorkbook);

        $em->flush();

        return $this->render('checkin/excel-import-result.html.twig', [
            'importer' => $importer,
            'rejected' => $output['rejected'] ?? [],
            'accepted' => $output['accepted'] ?? [],
        ]);
    }

    private function mustFindImport(int $importId): ExcelImportWorkbook
    {
        $workbook = $this->getDoctrine()
            ->getManager()
            ->find(ExcelImportWorkbook::class, $importId);
        if (!$workbook) {
            throw new \InvalidArgumentException('Cannot find Import by ID');
        }

        return $workbook;
    }

    private function createJsonErrorResponse(string $msg): JsonResponse
    {
        return new JsonResponse([
            'errorMsg' => $msg,
        ], 400);
    }
}
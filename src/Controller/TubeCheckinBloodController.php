<?php

namespace App\Controller;

use App\Entity\ExcelImportWorkbook;
use App\ExcelImport\ExcelImporter;
use App\ExcelImport\TubeCheckinBloodImporter;
use App\ExcelImport\TubeCheckinSalivaImporter;
use App\Form\GenericExcelImportType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Actions for the Blood Tube check-in process. These tubes have been returned by
 * Participants and allow Technicians to acknowledge receipt.
 *
 * @Route(path="/checkin/blood")
 */
class TubeCheckinBloodController extends AbstractController
{
    /**
     * @Route(path="/import/start", name="checkin_blood_import_start")
     */
    public function importStart(Request $request, ExcelImporter $excelImporter)
    {
        $this->denyAccessUnlessGranted('ROLE_TUBE_CHECK_IN');

        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(GenericExcelImportType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $excelFile */
            $excelFile = $form->get('excelFile')->getData();

            $workbook = $excelImporter->createWorkbookFromUpload($excelFile);
            $em->persist($workbook);
            $em->flush();

            return $this->redirectToRoute('checkin_blood_import_preview', [
                'importId' => $workbook->getId(),
            ]);
        }

        return $this->render('excel-import/base-excel-import-start.html.twig', [
            'itemLabel' => 'Blood Tubes',
            'importForm' => $form->createView(),
        ]);
    }

    /**
     * @Route("/import/preview/{importId<\d+>}", name="checkin_blood_import_preview")
     */
    public function importPreview(int $importId, ExcelImporter $excelImporter)
    {
        $this->denyAccessUnlessGranted('ROLE_TUBE_CHECK_IN');

        $importingWorkbook = $this->mustFindImport($importId);
        $excelImporter->userMustHavePermissions($importingWorkbook);

        $importer = new TubeCheckinBloodImporter(
            $this->getDoctrine()->getManager(),
            $importingWorkbook->getFirstWorksheet()
        );

        $importer->process();
        $output = $importer->getOutput();

        return $this->render('checkin/blood/excel-import-preview.html.twig', [
            'importId' => $importId,
            'importer' => $importer,
            'rejected' => $output['rejected'] ?? [],
            'accepted' => $output['accepted'] ?? [],
            'importPreviewTemplate' => 'checkin/blood/excel-import-table.html.twig',
            'importCommitRoute' => 'checkin_blood_import_commit',
            'importCommitText' => 'Save Check-ins',
        ]);
    }

    /**
     * @Route("/import/commit/{importId<\d+>}", methods={"POST"}, name="checkin_blood_import_commit")
     */
    public function importCommit(int $importId, ExcelImporter $excelImporter)
    {
        $this->denyAccessUnlessGranted('ROLE_TUBE_CHECK_IN');

        $em = $this->getDoctrine()->getManager();

        $importingWorkbook = $this->mustFindImport($importId);
        $excelImporter->userMustHavePermissions($importingWorkbook);

        $importer = new TubeCheckinSalivaImporter(
            $em,
            $importingWorkbook->getFirstWorksheet()
        );
        $importer->process(true);
        $output = $importer->getOutput();

        // Clean up workbook from the database
        $em->remove($importingWorkbook);

        $em->flush();

        return $this->render('checkin/blood/excel-import-result.html.twig', [
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
}

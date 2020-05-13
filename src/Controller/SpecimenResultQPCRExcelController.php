<?php

namespace App\Controller;

use App\Entity\ExcelImportWorkbook;
use App\ExcelImport\ExcelImporter;
use App\ExcelImport\SpecimenResultQPCRImporter;
use App\Form\GenericExcelImportType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Import SpecimenResultQPCR with Excel
 *
 * @Route(path="/results/qpcr/excel-import")
 */
class SpecimenResultQPCRExcelController extends AbstractController
{
    /**
     * @Route("/start", name="qpcr_excel_import")
     */
    public function start(Request $request, ExcelImporter $excelImporter)
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

            return $this->redirectToRoute('qpcr_excel_import_preview', [
                'importId' => $workbook->getId(),
            ]);
        }

        return $this->render('excel-import/base-excel-import-start.twig', [
            'itemLabel' => 'Results',
            'importForm' => $form->createView(),
        ]);
    }

    /**
     * @Route("/preview/{importId<\d+>}", name="qpcr_excel_import_preview")
     */
    public function preview(int $importId, ExcelImporter $excelImporter)
    {
        $importingWorkbook = $this->mustFindImport($importId);
        $excelImporter->userMustHavePermissions($importingWorkbook);

        $importer = new SpecimenResultQPCRImporter(
            $this->getDoctrine()->getManager(),
            $importingWorkbook->getFirstWorksheet()
        );

        $output = $importer->process();

        return $this->render('results/qpcr/excel-import-preview.html.twig', [
            'importId' => $importId,
            'importer' => $importer,
            'createdResults' => $output['created'] ?? [],
            'updatedResults' => $output['updated'] ?? [],
            'importPreviewTemplate' => 'results/qpcr/excel-import-table.html.twig',
            'importCommitRoute' => 'qpcr_excel_import_commit',
            'importCommitText' => 'Save Results',
        ]);
    }

    /**
     * @Route("/commit/{importId<\d+>}", methods={"POST"}, name="qpcr_excel_import_commit")
     */
    public function commit(int $importId, ExcelImporter $excelImporter)
    {
        $em = $this->getDoctrine()->getManager();

        $importingWorkbook = $this->mustFindImport($importId);
        $excelImporter->userMustHavePermissions($importingWorkbook);

        $importer = new SpecimenResultQPCRImporter(
            $em,
            $importingWorkbook->getFirstWorksheet()
        );
        $importer->process(true);

        // Clean up workbook from the database
        $em->remove($importingWorkbook);

        $em->flush();

        return $this->render('results/qpcr/excel-import-result.html.twig', [
            'importer' => $importer,
            'createdResults' => $output['created'] ?? [],
            'updatedResults' => $output['updated'] ?? [],
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

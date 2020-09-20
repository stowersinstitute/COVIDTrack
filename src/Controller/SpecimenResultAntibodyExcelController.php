<?php

namespace App\Controller;

use App\Entity\ExcelImportWorkbook;
use App\ExcelImport\ExcelImporter;
use App\ExcelImport\SpecimenResultAntibodyImporter;
use App\Form\GenericExcelImportType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Import SpecimenResultAntibody with Excel
 *
 * @Route(path="/results/antibody/excel-import")
 */
class SpecimenResultAntibodyExcelController extends AbstractController
{
    /**
     * @Route("/start", name="antibody_excel_import")
     */
    public function start(Request $request, ExcelImporter $excelImporter)
    {
        $this->denyAccessUnlessGranted('ROLE_RESULTS_EDIT');

        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(GenericExcelImportType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $excelFile */
            $excelFile = $form->get('excelFile')->getData();

            $workbook = $excelImporter->createWorkbookFromUpload($excelFile);
            $em->persist($workbook);
            $em->flush();

            return $this->redirectToRoute('antibody_excel_import_preview', [
                'importId' => $workbook->getId(),
            ]);
        }

        return $this->render('excel-import/base-excel-import-start.html.twig', [
            'itemLabel' => 'Antibody Results',
            'importForm' => $form->createView(),
        ]);
    }

    /**
     * @Route("/preview/{importId<\d+>}", name="antibody_excel_import_preview")
     */
    public function preview(int $importId, ExcelImporter $excelImporter)
    {
        $this->denyAccessUnlessGranted('ROLE_RESULTS_EDIT');

        $importingWorkbook = $this->mustFindImport($importId);
        $excelImporter->userMustHavePermissions($importingWorkbook);

        $importer = new SpecimenResultAntibodyImporter(
            $this->getDoctrine()->getManager(),
            $importingWorkbook->getFirstWorksheet(),
            $importingWorkbook->getFilename()
        );

        $importer->process();
        $output = $importer->getOutput();

        return $this->render('results/antibody/excel-import-preview.html.twig', [
            'importId' => $importId,
            'importer' => $importer,
            'displayMultiWorksheetWarning' => count($importingWorkbook->getWorksheets()) > 1,
            'createdResults' => $output['created'] ?? [],
            'updatedResults' => $output['updated'] ?? [],
            'importPreviewTemplate' => 'results/antibody/excel-import-table.html.twig',
            'importCommitRoute' => 'antibody_excel_import_commit',
            'importCommitText' => 'Save Results',
        ]);
    }

    /**
     * @Route("/commit/{importId<\d+>}", methods={"POST"}, name="antibody_excel_import_commit")
     */
    public function commit(int $importId, ExcelImporter $excelImporter)
    {
        $this->denyAccessUnlessGranted('ROLE_RESULTS_EDIT');

        $em = $this->getDoctrine()->getManager();

        $importingWorkbook = $this->mustFindImport($importId);
        $excelImporter->userMustHavePermissions($importingWorkbook);

        $importer = new SpecimenResultAntibodyImporter(
            $em,
            $importingWorkbook->getFirstWorksheet(),
            $importingWorkbook->getFilename()
        );
        $importer->process(true);

        // Clean up workbook from the database
        $em->remove($importingWorkbook);

        $em->flush();

        return $this->render('results/antibody/excel-import-result.html.twig', [
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

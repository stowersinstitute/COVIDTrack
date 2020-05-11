<?php


namespace App\Controller;


use App\Entity\ExcelImportWorkbook;
use App\ExcelImport\SpecimenIntakeImporter;
use App\Form\GenericExcelImportType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Manages actions for the specimen intake process where specimens are delivered to the
 * testing facility and technicians acknowledge receipt
 *
 * @Route(path="/testing/specimen-intake")
 */
class SpecimenIntakeController extends AbstractController
{
    /**
     * @route(path="/upload/start", name="specimen_intake_start")
     */
    public function startUpload(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(GenericExcelImportType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $excelFile */
            $excelFile = $form->get('excelFile')->getData();

            $workbook = ExcelImportWorkbook::createFromUpload($excelFile);
            $em->persist($workbook);
            $em->flush();

            return $this->redirectToRoute('specimen_intake_import_preview', [
                'importId' => $workbook->getId(),
            ]);
        }

        return $this->render('excel-import/base-excel-import-start.twig', [
            'itemLabel' => 'Specimen Check-in Data',
            'importForm' => $form->createView(),
        ]);
    }

    /**
     * @Route("/upload/preview/{importId<\d+>}", name="specimen_intake_import_preview")
     */
    public function importPreview(int $importId)
    {
        $importingWorkbook = $this->getDoctrine()
            ->getManager()
            ->find(ExcelImportWorkbook::class, $importId);

        $importer = new SpecimenIntakeImporter($importingWorkbook->getFirstWorksheet());
        $importer->setEntityManager($this->getDoctrine()->getManager());

        $importer->process();

        return $this->render('excel-import/base-excel-import-preview.html.twig', [
            'importId' => $importId,
            'importer' => $importer,
            'importPreviewTemplate' => 'specimen-intake/import-table.html.twig',
            'importCommitRoute' => 'specimen_intake_import_commit',
            'importCommitText' => 'Save Check-in',
        ]);
    }

    /**
     * @Route("/upload/commit/{importId<\d+>}", methods={"POST"}, name="specimen_intake_import_commit")
     */
    public function importCommit(int $importId)
    {
        $em = $this->getDoctrine()
            ->getManager();

        $importingWorkbook = $em
            ->find(ExcelImportWorkbook::class, $importId);

        $importer = new SpecimenIntakeImporter($importingWorkbook->getFirstWorksheet());
        $importer->setEntityManager($em);

        $importer->process(true);

        $affectedTubes = $importer->getOutput();

        // Clean up workbook from the database
        $em->remove($importingWorkbook);

        return $this->render('excel-import/base-excel-import-result.html.twig', [
            'importer' => $importer,
            'importResultTemplate' => 'specimen-intake/import-table.html.twig',
        ]);
    }
}
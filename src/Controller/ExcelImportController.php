<?php


namespace App\Controller;


use App\Entity\ExcelImportWorkbook;
use App\Form\GenericExcelImportType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ExcelImportController extends AbstractController
{
    /**
     * @Route("/excel-import/choose-file", name="excel_import_choose_file")
     */
    public function chooseFile(Request $request)
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

            return $this->redirectToRoute($request->get('previewRoute'), [
                'importId' => $workbook->getId(),
            ]);
        }

        return $this->render('excel-import/wizard-start.html.twig', [
            'importForm' => $form->createView(),
        ]);
    }
}
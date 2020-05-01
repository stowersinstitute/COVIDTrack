<?php


namespace App\Controller;


use App\Form\GenericExcelImportType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
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
        $form = $this->createForm(GenericExcelImportType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $excelFile */
            $excelFile = $form->get('excelFile')->getData();

            // Create directly from the excel file, can use this if we're processing in the controller
            $reader = new Xlsx();
            $spreadsheet = $reader->load($excelFile->getRealPath());
        }

        return $this->render('excel-import/wizard-start.html.twig', [
            'importForm' => $form->createView(),
        ]);
    }
}
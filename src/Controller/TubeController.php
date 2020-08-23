<?php

namespace App\Controller;

use App\Entity\ExcelImportWorkbook;
use App\Entity\LabelPrinter;
use App\Entity\Tube;
use App\ExcelImport\ExcelImporter;
use App\ExcelImport\TubeImporter;
use App\Form\GenericExcelImportType;
use App\Label\MBSBloodTubeLabelBuilder;
use App\Label\SpecimenIntakeLabelBuilder;
use App\Label\ZplPrinting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Interact with Tubes.
 *
 * @Route(path="/tubes")
 */
class TubeController extends AbstractController
{
    /**
     * List all Tubes
     *
     * @Route(path="/", methods={"GET", "POST"}, name="tube_list")
     */
    public function list(Request $request, EntityManagerInterface $em, ZplPrinting $zpl)
    {
        $this->denyAccessUnlessGranted('ROLE_PRINT_TUBE_LABELS');

        $tubes = $this->getDoctrine()
            ->getRepository(Tube::class)
            ->findBy([], ['createdAt' => 'DESC']);

        $form = $this->createFormBuilder()
            ->add('printer', EntityType::class, [
                'class' => LabelPrinter::class,
                'choice_name' => 'title',
                'required' => true,
                'empty_data' => "",
                'placeholder' => '- None -'
            ])
            ->add('labelType', ChoiceType::class, [
                'label' => 'Label Type',
                'choices' => [
                    'Saliva: Square 0.75" ' => SpecimenIntakeLabelBuilder::class,
                    'Blood: MBS Tube 1" x 0.25"' => MBSBloodTubeLabelBuilder::class,
                ],
                'placeholder' => '- Select -',
                'required' => true,
            ])
            ->add('print', SubmitType::class, [
                'label' => 'Re-Print Selected Tubes',
                'attr' => ['class' => 'btn-primary'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $tubesIds = $request->request->get('tubes', []);

            $printTubes = $em->getRepository(Tube::class)->findBy(['accessionId' => $tubesIds]);

            $printer = $em->getRepository(LabelPrinter::class)->find($data['printer']);
            $builderClass = $data['labelType'];

            $builder = new $builderClass;
            $builder->setPrinter($printer);

            foreach ($printTubes as $tube) {
                $builder->setTube($tube);
                $zpl->printBuilder($builder);
            }
        }


        return $this->render('tube/tube-list.html.twig', [
            'tubes' => $tubes,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/excel-import/start", name="tube_excel_import")
     */
    public function excelImport(Request $request, ExcelImporter $excelImporter)
    {
        $this->denyAccessUnlessGranted('ROLE_PRINT_TUBE_LABELS');

        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(GenericExcelImportType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $excelFile */
            $excelFile = $form->get('excelFile')->getData();

            $workbook = $excelImporter->createWorkbookFromUpload($excelFile);
            $em->persist($workbook);
            $em->flush();

            return $this->redirectToRoute('tube_excel_import_preview', [
                'importId' => $workbook->getId(),
            ]);
        }

        return $this->render('excel-import/base-excel-import-start.html.twig', [
            'itemLabel' => 'Pre-labeled Tubes',
            'importForm' => $form->createView(),
        ]);
    }

    /**
     * @Route("/excel-import/preview/{importId<\d+>}", name="tube_excel_import_preview")
     */
    public function excelImportPreview(
        int $importId,
        ExcelImporter $excelImporter
    ) {
        $this->denyAccessUnlessGranted('ROLE_PRINT_TUBE_LABELS');

        $em = $this->getDoctrine()->getManager();

        $importingWorkbook = $this->mustFindImport($importId);
        $excelImporter->userMustHavePermissions($importingWorkbook);

        $importer = new TubeImporter($importingWorkbook->getFirstWorksheet());
        $importer->setEntityManager($em);

        $importer->process();

        return $this->render('tube/excel-import-preview.html.twig', [
            'importId' => $importId,
            'importer' => $importer,
            'displayMultiWorksheetWarning' => count($importingWorkbook->getWorksheets()) > 1,
            'importPreviewTemplate' => 'tube/excel-import-table.html.twig',
            'importCommitRoute' => 'tube_excel_import_commit',
            'importCommitText' => 'Save Tubes',
        ]);
    }

    /**
     * @Route("/excel-import/commit/{importId<\d+>}", methods={"POST"}, name="tube_excel_import_commit")
     */
    public function excelImportCommit(
        int $importId,
        ExcelImporter $excelImporter
    ) {
        $this->denyAccessUnlessGranted('ROLE_PRINT_TUBE_LABELS');

        $em = $this->getDoctrine()->getManager();

        $importingWorkbook = $this->mustFindImport($importId);
        $excelImporter->userMustHavePermissions($importingWorkbook);

        $importer = new TubeImporter($importingWorkbook->getFirstWorksheet());
        $importer->setEntityManager($em);
        $importer->process(true);

        // Clean up workbook from the database
        $em->remove($importingWorkbook);

        $em->flush();

        return $this->render('excel-import/base-excel-import-result.html.twig', [
            'importer' => $importer,
            'importResultTemplate' => 'tube/excel-import-table.html.twig',
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

<?php

namespace App\Controller;

use App\Entity\ExcelImportWorkbook;
use App\Entity\LabelPrinter;
use App\Entity\Tube;
use App\ExcelImport\ExcelImporter;
use App\ExcelImport\TubeImporter;
use App\Form\GenericExcelImportType;
use App\Form\TubeFilterForm;
use App\Label\MBSBloodTubeLabelBuilder;
use App\Label\SpecimenIntakeLabelBuilder;
use App\Label\ZplPrinting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    public function list(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_PRINT_TUBE_LABELS');

        // Explicitly use FormFactory to remove form name from GET params for cleaner URL
        $filterForm = $this->get('form.factory')->createNamed('', TubeFilterForm::class);
        $filterForm->handleRequest($request);
        $formData = [];
        if ($filterForm->isSubmitted() && $filterForm->isValid()) {
            $formData = $filterForm->getData();
        }

        $tubes = $this->getDoctrine()
            ->getRepository(Tube::class)
            ->filterByFormData($formData);

        return $this->render('tube/tube-list.html.twig', [
            'tubes' => $tubes,
            'printForm' => $this->getPrintForm()->createView(),
            'filterForm' => $filterForm->createView(),
        ]);
    }

    /**
     * Print Tube labels.
     *
     * Required POST params:
     *
     * - tubes (string[]) Tube.accessionId to print
     *
     * @Route("/print", methods={"POST"}, name="tube_print")
     */
    public function print(Request $request, EntityManagerInterface $em, ZplPrinting $zpl)
    {
        $this->denyAccessUnlessGranted('ROLE_PRINT_TUBE_LABELS');

        $form = $this->getPrintForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            $accessionIds = $request->request->get('tubes', []);

            /** @var LabelPrinter $printer */
            $printer = $formData['printer'];

            $builderClass = $formData['labelType'];
            $builder = new $builderClass; // NOTE: $builderClass validated by Form
            $builder->setPrinter($printer);

            $printTubes = $em->getRepository(Tube::class)
                ->findBy(['accessionId' => $accessionIds]);

            foreach ($printTubes as $tube) {
                $builder->setTube($tube);
                $zpl->printBuilder($builder);
            }

            if (count($printTubes) > 0) {
                $this->addFlash('success', sprintf('Labels sent to printer %s.', $printer->getTitle()));
            }
        }

        return $this->redirect($request->headers->get('referer'));
    }

    /**
     * Set selected Tubes to a specific Tube::WEBHOOK_STATUS_* constant.
     *
     * Required params:
     *
     * - tubeAccessionIds (string[]) Array of Tube.accessionId to publish
     * - webHookStatus (string) Tube::WEBHOOK_STATUS_* constant value
     *
     * @Route(path="/web-hook/status", methods={"POST"}, name="tube_web_hook_status")
     */
    public function webhookStatus(Request $request, EntityManagerInterface $em)
    {
        $this->denyAccessUnlessGranted('ROLE_WEB_HOOKS', 'Web Hooks Access Required', 'You must have Web Hooks permission to set Tube Web Hook status');

        if (!$request->request->has('tubeAccessionIds')) {
            return $this->createJsonErrorResponse('Param tubeAccessionIds is required');
        }
        if (!$request->request->has('webHookStatus')) {
            return $this->createJsonErrorResponse('Param webHookStatus is required');
        }

        // Verify valid web hook status
        $webHookStatus = $request->request->get('webHookStatus');
        try {
            Tube::ensureValidWebHookStatus($webHookStatus);
        } catch (\InvalidArgumentException $e) {
            // Return error to client as JSON errorMsg
            return $this->createJsonErrorResponse($e->getMessage());
        }

        // Convert to Tubes
        $repo = $em->getRepository(Tube::class);
        foreach ($request->request->get('tubeAccessionIds') as $accessionId) {
            $tube = $repo->findOneByAccessionId($accessionId);
            if (!$tube) {
                return $this->createJsonErrorResponse(sprintf('Cannot find Tube by Accession ID: "%s"', $accessionId));
            }

            // Finally set web hook status from client
            try {
                $tube->setWebHookStatus($webHookStatus, 'Status manually set');
            } catch (\LogicException $e) {
                // Exception thrown when required data missing.
                // Return error to client as JSON errorMsg.
                return $this->createJsonErrorResponse(sprintf('Tube "%s": %s', $accessionId, $e->getMessage()));
            }
        }

        $em->flush();

        return new JsonResponse([
            'success' => true,
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

    private function getPrintForm(): FormInterface
    {
        return $this->createFormBuilder()
            ->add('printer', EntityType::class, [
                'class' => LabelPrinter::class,
                'choice_name' => 'title',
                'required' => true,
                'empty_data' => "",
                'placeholder' => '- Select -'
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
            ->setAction($this->generateUrl('tube_print'))
            ->getForm();
    }

    private function createJsonErrorResponse(string $msg): JsonResponse
    {
        return new JsonResponse([
            'errorMsg' => $msg,
        ], 400);
    }
}

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
use App\Tecan\CannotReadOutputFileException;
use App\Tecan\SpecimenIdNotFoundException;
use App\Tecan\TecanOutput;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\File;

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
     * @Route(path="/", methods={"GET", "POST"})
     */
    public function list(Request $request, EntityManagerInterface $em, ZplPrinting $zpl)
    {
        $this->denyAccessUnlessGranted('ROLE_PRINT_TUBE_LABELS');

        $tubes = $this->getDoctrine()
            ->getRepository(Tube::class)
            ->findBy([], ['accessionId' => 'desc']);

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
     * Accepts file upload from a Tecan plate reader in tab-delimited format.
     * Replaces file's Tube Accession IDs with Specimen Accession IDs.
     * Returns modified data as a file download.
     *
     * @Route(path="/tecan-to-specimen-ids", methods={"GET", "POST"}, name="tecan_to_specimen_ids")
     */
    public function tecanToSpecimenId(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_RESULTS_EDIT');

        $form = $this->createFormBuilder()
            ->add('tecanFile', FileType::class, [
                'label' => 'Tecan File',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => ini_get('upload_max_filesize'),
                    ])
                ]
            ])
            ->add('upload', SubmitType::class, [
                'label' => 'Convert and Download',
                'attr' => ['class' => 'btn-primary'],
            ])
            ->getForm();

        $form->handleRequest($request);

        /**
         * User-visible error messages about file parsing procses
         * @var string[]
         */
        $errors = [];

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $uploadedFile */
            $uploadedFile = $form->get('tecanFile')->getData();

            $fileinfo = [
                'filename' => pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME),
                'extension' => pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_EXTENSION),
                'filename.extension' => pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_BASENAME),
            ];

            $tubeRepo = $this->getDoctrine()->getManager()->getRepository(Tube::class);

            // Exceptions report errors back to user
            try {
                // TecanOutput object knows about uploaded file format
                $tecan = TecanOutput::fromUploadFile($uploadedFile);

                // Convert to Accession IDs
                $output = $tecan->convertTubesToSpecimens($tubeRepo);
            } catch (SpecimenIdNotFoundException | CannotReadOutputFileException $e) {
                $errors[] = $e->getMessage();
            } catch (\Exception | \Throwable $e) {
                $errors[] = 'A server error prevented conversion. Please contact application support.';
            }

            if (empty($errors)) {
                // Return data as file download with original MIME-type and extension
                $originalMineType = $uploadedFile->getClientMimeType();
                $originalFilename = $fileinfo['filename.extension'];

                $responseText = implode("", $output);

                return $this->fileDownloadResponseFromText($responseText, $originalMineType, $originalFilename);
            }

            // Fall through to redisplay upload form with errors
        }

        return $this->render('tube/tecan-to-specimen-ids.html.twig', [
            'form' => $form->createView(),
            'errors' => $errors,
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

        return $this->render('excel-import/base-excel-import-start.twig', [
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

        return $this->render('excel-import/base-excel-import-preview.html.twig', [
            'importId' => $importId,
            'importer' => $importer,
            'importPreviewTemplate' => 'tube/excel-import-table.html.twig',
            'importCommitRoute' => 'tube_excel_import_commit',
            'importCommitText' => 'Import Tubes',
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

    /**
     * Creates file download Response for given string content.
     *
     * Use this instead of BinaryDownloadResponse() so $content can be read from
     * memory instead of a temp file.
     */
    private function fileDownloadResponseFromText(string $content, string $mimeType, string $filename): Response
    {
        $R = new Response();

        // Headers to force download and set filename
        $R->headers->set('Cache-Control', 'private');
        $R->headers->set('Content-type', $mimeType);
        $R->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '";');
        $R->headers->set('Content-length', strlen($content));

        $R->setContent($content);

        return $R;
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

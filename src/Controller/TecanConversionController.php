<?php

namespace App\Controller;

use App\Entity\AppUser;
use App\Entity\ExcelImportWorkbook;
use App\Entity\Tube;
use App\ExcelImport\ExcelImporter;
use App\ExcelImport\TecanImporter;
use App\Tecan\TecanOutput;
use App\Util\EntityUtils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Constraints\File;

/**
 * Import data from Tecan output file
 *
 * @Route(path="/tecan/import")
 */
class TecanConversionController extends AbstractController
{
    /**
     * @Route("/start", name="tecan_import_start")
     */
    public function start(Request $request)
    {
        $this->denyAccessUnlessGrantedPermission();

        $em = $this->getDoctrine()->getManager();
        $form = $this->createFormBuilder()
            ->add('tecanFile', FileType::class, [
                'label' => 'Tecan Output File',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => ini_get('upload_max_filesize'),
                    ])
                ]
            ])
            ->add('upload', SubmitType::class, [
                'label' => 'Upload',
                'attr' => ['class' => 'btn-primary'],
            ])
            ->getForm();

        $form->handleRequest($request);

        /**
         * User-visible error messages about file parsing process
         * @var string[]
         */
        $errors = [];

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $importFile */
            $importFile = $form->get('tecanFile')->getData();

            // Exceptions report errors back to user
            $errors = [];
            try {
                // Create Workbook for import
                $workbook = TecanImporter::createExcelImportWorkbookFromUpload($importFile, $this->getUser());
                $em->persist($workbook);

                // Create conversion of uploaded file, replacing Tube IDs with Specimen IDs
                $path = $importFile->getRealPath();
                $tubeRepo = $this->getDoctrine()->getManager()->getRepository(Tube::class);
                $rawConversionOutput = TecanOutput::convertTubesToSpecimens($path, $tubeRepo);

                // Write file like 123456789_MyFile.xls
                $outputPath = $this->getConversionOutputPathByWorkbook($workbook);
                if (!file_put_contents($outputPath, $rawConversionOutput)) {
                    throw new \RuntimeException('Cannot write output file');
                }

                // Save ExcelImportWorkbook
                $em->flush();

                return $this->redirectToRoute('tecan_import_preview', [
                    'importId' => $workbook->getId(),
                ]);
            } catch (\Exception | \Throwable $e) {
                $errors[] = 'A server error prevented conversion. Please contact application support.';
                $errors[] = 'Error: ' . $e->getMessage();
            }

            // Fall through to display errors
        }

        return $this->render('tecan-conversion/start.html.twig', [
            'itemLabel' => 'Results',
            'form' => $form->createView(),
            'errors' => $errors,
            'coordinates' => [
                'firstTubeRow' => TecanImporter::STARTING_ROW,
                'wellPlateBarcode' => [
                    'column' => TecanImporter::BARCODE_COLUMN,
                    'row' => TecanImporter::BARCODE_ROW,
                ],
                'wellPosition' => [
                    'column' => TecanImporter::WELL_POSITION_COLUMN,
                    'row' => TecanImporter::WELL_POSITION_ROW,
                ],
                'tubeAccessionId' => [
                    'column' => TecanImporter::TUBE_ID_COLUMN,
                    'row' => TecanImporter::TUBE_ID_ROW,
                ],
            ],
        ]);
    }

    /**
     * @Route("/preview/{importId<\d+>}", name="tecan_import_preview")
     */
    public function preview(int $importId)
    {
        $this->denyAccessUnlessGrantedPermission();

        $importingWorkbook = $this->mustFindImport($importId);
        $this->userMustHavePermissions($importingWorkbook, $this->getUser());

        $importer = new TecanImporter(
            $this->getDoctrine()->getManager(),
            $importingWorkbook->getFirstWorksheet()
        );

        $importer->process();
        $output = $importer->getOutput();

        return $this->render('tecan-conversion/import-preview.html.twig', [
            'importId' => $importId,
            'importer' => $importer,
            'createdResults' => $output['created'] ?? [],
            'updatedResults' => $output['updated'] ?? [],
            'importPreviewTemplate' => 'tecan-conversion/import-table.html.twig',
            'importCommitRoute' => 'tecan_import_commit',
            'importCommitText' => 'Save Well Plate Data',
        ]);
    }

    /**
     * @Route("/commit/{importId<\d+>}", methods={"POST"}, name="tecan_import_commit")
     */
    public function commit(int $importId, ExcelImporter $excelImporter)
    {
        $this->denyAccessUnlessGrantedPermission();

        $importingWorkbook = $this->mustFindImport($importId);
        $this->userMustHavePermissions($importingWorkbook, $this->getUser());

        $em = $this->getDoctrine()->getManager();
        $importer = new TecanImporter(
            $em,
            $importingWorkbook->getFirstWorksheet()
        );

        $importer->process(true);

        $em->flush();

        return $this->render('tecan-conversion/import-saved.html.twig', [
            'importId' => $importId,
            'importer' => $importer,
            'createdResults' => $output['created'] ?? [],
            'updatedResults' => $output['updated'] ?? [],
        ]);
    }

    /**
     * @Route("/download/{importId<\d+>}", methods={"GET"}, name="tecan_download_conversion")
     */
    public function downloadConversion(int $importId)
    {
        $this->denyAccessUnlessGrantedPermission();

        $workbook = $this->mustFindImport($importId);
        $this->userMustHavePermissions($workbook, $this->getUser());

        // Get conversion file written during original import
        $outputPath = $this->getConversionOutputPathByWorkbook($workbook);
        $outputText = file_get_contents($outputPath);

        // Return data as file download with original filename and MIME-type
        $originalMineType = $workbook->getFileMimeType();
        $originalFilename = $workbook->getFilename();

        // Create file download response
        $response = $this->fileDownloadResponseFromText($outputText, $originalMineType, $originalFilename);

        // Clean up workbook from the database
        $em = $this->getDoctrine()->getManager();
        $em->remove($workbook);
        $em->flush();

        return $response;
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

    private function denyAccessUnlessGrantedPermission()
    {
        $this->denyAccessUnlessGranted('ROLE_RESULTS_EDIT');
    }

    private function userMustHavePermissions(ExcelImportWorkbook $workbook, AppUser $user)
    {
        if (!EntityUtils::isSameEntity($user, $workbook->getUploadedBy())) {
            throw new AccessDeniedException('You do not have permission to access this import');
        }
    }

    private function getConversionOutputPathByWorkbook(ExcelImportWorkbook $workbook): string
    {
        // For example: 1590629589_RPE1P7.csv
        $outputFilename = sprintf("%d_%s", $workbook->getUploadedAt()->format('U'), $workbook->getFilename());

        // Saved in Symfony cache dir guaranteeing readable/writeable by application code.
        // Files cleaned up when doing: bin/console cache:clear
        $cacheDir = $this->getParameter('kernel.cache_dir');

        return sprintf("%s/%s", $cacheDir, $outputFilename);
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
}

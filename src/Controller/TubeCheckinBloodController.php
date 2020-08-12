<?php

namespace App\Controller;

use App\Entity\AppUser;
use App\Entity\ExcelImportWorkbook;
use App\Entity\Tube;
use App\ExcelImport\TubeCheckinBloodImporter;
use App\Form\GenericExcelImportType;
use App\Util\EntityUtils;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

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
    public function importStart(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_TUBE_CHECK_IN');

        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(GenericExcelImportType::class);

        $form->handleRequest($request);

        /**
         * User-visible error messages about file parsing process
         * @var string[]
         */
        $errors = [];

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $excelFile */
            $excelFile = $form->get('excelFile')->getData();

            try {
                $uploadedWorkbook = TubeCheckinBloodImporter::createExcelImportWorkbookFromUpload($excelFile, $this->getUser());
                $em->persist($uploadedWorkbook);
                $em->flush();

                // Create conversion of uploaded file, replacing Tube IDs with Specimen IDs
                $path = $excelFile->getRealPath();
                $tubeRepo = $this->getDoctrine()->getManager()->getRepository(Tube::class);
                $outputSpreadsheet = TubeCheckinBloodImporter::convertTubesToSpecimens($path, $tubeRepo);

                // Write file like 123456789_MyFile.xlsx
                $readerReflection = new \ReflectionClass(IOFactory::createReaderForFile($path));
                $writer = IOFactory::createWriter($outputSpreadsheet, $readerReflection->getShortName());
                $outputPath = $this->getConversionOutputPathByWorkbook($uploadedWorkbook);
                $writer->save($outputPath);

                // Redirect to next step
                return $this->redirectToRoute('checkin_blood_import_preview', [
                    'importId' => $uploadedWorkbook->getId(),
                ]);
            } catch (\Exception | \Throwable $e) {
                $errors[] = 'A server error prevented upload. Please contact application support.';
                $errors[] = 'Error: ' . $e->getMessage();
            }

            // Fall through to display errors
        }

        return $this->render('excel-import/base-excel-import-start.html.twig', [
            'itemLabel' => 'Blood Tubes',
            'importForm' => $form->createView(),
            'errors' => $errors,
        ]);
    }

    /**
     * @Route("/import/preview/{importId<\d+>}", name="checkin_blood_import_preview")
     */
    public function importPreview(int $importId)
    {
        $this->denyAccessUnlessGranted('ROLE_TUBE_CHECK_IN');

        $importingWorkbook = $this->mustFindImport($importId);
        $this->userMustHavePermissions($importingWorkbook, $this->getUser());

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
    public function importCommit(int $importId)
    {
        $this->denyAccessUnlessGranted('ROLE_TUBE_CHECK_IN');

        $em = $this->getDoctrine()->getManager();

        $importingWorkbook = $this->mustFindImport($importId);
        $this->userMustHavePermissions($importingWorkbook, $this->getUser());

        $importer = new TubeCheckinBloodImporter(
            $em,
            $importingWorkbook->getFirstWorksheet()
        );
        $importer->process(true);
        $output = $importer->getOutput();

        $em->flush();

        return $this->render('checkin/blood/excel-import-result.html.twig', [
            'importId' => $importId,
            'importer' => $importer,
            'rejected' => $output['rejected'] ?? [],
            'accepted' => $output['accepted'] ?? [],
        ]);
    }

    /**
     * @Route("/import/download/{importId<\d+>}", methods={"GET"}, name="checkin_blood_download_conversion")
     */
    public function downloadConversion(int $importId)
    {
        $this->denyAccessUnlessGranted('ROLE_TUBE_CHECK_IN');

        $workbook = $this->mustFindImport($importId);
        $this->userMustHavePermissions($workbook, $this->getUser());

        // Get conversion file written during original import
        $outputPath = $this->getConversionOutputPathByWorkbook($workbook);
        $outputText = file_get_contents($outputPath);
        if (!$outputText) {
            throw new \RuntimeException('Cannot read path ' . $outputPath);
        }

        // Return data as file download with appended original filename and MIME-type
        $originalMineType = $workbook->getFileMimeType();
        $conversionFilename = sprintf('%s-%s','specimen-conversion', $workbook->getFilename());

        // Create file download response
        $response = $this->fileDownloadResponseFromText($outputText, $originalMineType, $conversionFilename);

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

    private function userMustHavePermissions(ExcelImportWorkbook $workbook, AppUser $user)
    {
        if (!EntityUtils::isSameEntity($user, $workbook->getUploadedBy())) {
            throw new AccessDeniedException('You do not have permission to access this import');
        }
    }

    private function getConversionOutputPathByWorkbook(ExcelImportWorkbook $workbook): string
    {
        // For example: 1590629589_original-filename-goes-here.xlsx
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

<?php

namespace App\Controller;

use App\Entity\AppUser;
use App\Entity\ExcelImportWorkbook;
use App\Entity\Tube;
use App\ExcelImport\ExcelImporter;
use App\ExcelImport\SpecimenResultQPCRImporter;
use App\ExcelImport\TecanImporter;
use App\Form\GenericExcelImportType;
use App\Tecan\CannotReadOutputFileException;
use App\Tecan\SpecimenIdNotFoundException;
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
 * @Route(path="/results/tecan/import")
 */
class TecanConversionController extends AbstractController
{
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

        return $this->render('tecan/index.html.twig', [
            'form' => $form->createView(),
            'errors' => $errors,
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



    /**
     * @Route("/start", name="tecan_import_start")
     */
    public function start(Request $request)
    {
        $this->denyAccessUnlessGrantedPermission();

        $em = $this->getDoctrine()->getManager();
//        $form = $this->createForm(GenericExcelImportType::class);
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

            $workbook = TecanImporter::createWorkbookFromUpload($importFile, $this->getUser());
            $em->persist($workbook);
            $em->flush();

            return $this->redirectToRoute('tecan_import_preview', [
                'importId' => $workbook->getId(),
            ]);




            /** @var UploadedFile $uploadedFile */
//            $uploadedFile = $form->get('tecanFile')->getData();
//
//            $fileinfo = [
//                'filename' => pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME),
//                'extension' => pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_EXTENSION),
//                'filename.extension' => pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_BASENAME),
//            ];
//
//            $tubeRepo = $this->getDoctrine()->getManager()->getRepository(Tube::class);
//
//            // Exceptions report errors back to user
//            try {
//                // TecanOutput object knows about uploaded file format
//                $tecan = TecanOutput::fromUploadFile($uploadedFile);
//
//                // Convert to Accession IDs
//                $output = $tecan->convertTubesToSpecimens($tubeRepo);
//            } catch (SpecimenIdNotFoundException | CannotReadOutputFileException $e) {
//                $errors[] = $e->getMessage();
//            } catch (\Exception | \Throwable $e) {
//                $errors[] = 'A server error prevented conversion. Please contact application support.';
//            }
//
//            if (empty($errors)) {
//                // Return data as file download with original MIME-type and extension
//                $originalMineType = $uploadedFile->getClientMimeType();
//                $originalFilename = $fileinfo['filename.extension'];
//
//                $responseText = implode("", $output);
//
//                return $this->fileDownloadResponseFromText($responseText, $originalMineType, $originalFilename);
//            }

            // Fall through to redisplay upload form with errors
        }

        return $this->render('tecan/start.html.twig', [
            'itemLabel' => 'Results',
            'form' => $form->createView(),
            'errors' => $errors,
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

        $output = $importer->process();

//        return $this->render('results/qpcr/excel-import-preview.html.twig', [
        return $this->render('tecan/import-preview.html.twig', [
            'importId' => $importId,
            'importer' => $importer,
            'createdResults' => $output['created'] ?? [],
            'updatedResults' => $output['updated'] ?? [],
            'importPreviewTemplate' => 'results/qpcr/excel-import-table.html.twig',
            'importCommitRoute' => 'tecan_import_commit',
            'importCommitText' => 'Save Results',
        ]);
    }

    /**
     * @Route("/commit/{importId<\d+>}", methods={"POST"}, name="tecan_import_commit")
     */
    public function commit(int $importId, ExcelImporter $excelImporter)
    {
        $this->denyAccessUnlessGrantedPermission();

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
}

<?php

namespace App\Controller;

use App\Entity\LabelPrinter;
use App\Entity\Tube;
use App\Label\SpecimenIntakeLabelBuilder;
use App\Label\ZplPrinting;
use App\Tecan\TecanOutput;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
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
        $tubes = $this->getDoctrine()
            ->getRepository(Tube::class)
            ->findAll();

        $form = $this->createFormBuilder()
            ->add('printer', EntityType::class, [
                'class' => LabelPrinter::class,
                'choice_name' => 'title',
                'required' => true,
                'empty_data' => "",
                'placeholder' => '- None -'
            ])
            ->add('print', SubmitType::class, [
                'label' => 'Print Selected'
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $tubesIds = $request->request->get('tubes', []);

            $printTubes = $em->getRepository(Tube::class)->findBy(['accessionId' => $tubesIds]);

            $printer = $em->getRepository(LabelPrinter::class)->find($data['printer']);

            $builder = new SpecimenIntakeLabelBuilder();
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
        $form = $this->createFormBuilder()
            ->add('tecanFile', FileType::class, [
                'label' => 'Tecan File',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => ini_get('upload_max_filesize'),
                        'mimeTypes' => [
                            'application/octet-stream', // Reported for tab-delimited file
                        ]
                    ])
                ]
            ])
            ->add('upload', SubmitType::class, [
                'label' => 'Convert and Download',
                'attr' => ['class' => 'btn-primary'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $uploadedFile */
            $uploadedFile = $form->get('tecanFile')->getData();

            $fileinfo = [
                'filename' => pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME),
                'extension' => pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_EXTENSION),
                'filename.extension' => pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_BASENAME),
            ];

            // TecanOutput object knows about uploaded file format
            $tecan = TecanOutput::fromUploadFile($uploadedFile);

            // Update Tubes to Specimens in memory
            $tubeRepo = $this->getDoctrine()->getManager()->getRepository(Tube::class);
            $output = $tecan->convertTubesToSpecimens($tubeRepo);

            // Return data as file download with original MIME-type and extension
            $originalMineType = $uploadedFile->getClientMimeType();
            $originalFilename = $fileinfo['filename.extension'];

            $responseText = implode("", $output);

            return $this->fileDownloadResponseFromText($responseText, $originalMineType, $originalFilename);
        }

        return $this->render('tube/tecan-to-specimen-ids.html.twig', [
            'form' => $form->createView()
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
}

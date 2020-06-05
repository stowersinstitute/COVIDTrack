<?php


namespace App\Controller;


use App\Entity\LabelPrinter;
use App\Entity\Tube;
use App\Form\LabelPrinterType;
use App\Label\GenericTextLabelBuilder;
use App\Label\MBSBloodTubeLabelBuilder;
use App\Label\SpecimenIntakeLabelBuilder;
use App\Label\ZplImage;
use App\Label\ZplPrinting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Zpl\Printer;

/**
 * Perform actions related to Label Printers.
 *
 * @Route(path="/label-printers")
 */
class LabelPrinterController extends AbstractController
{
    /**
     * Form to print new labels for collection Tubes distributed to Participants.
     *
     * @Route("/print-tube-labels", name="app_label_printer_print_tube_labels")
     */
    public function printTubeLabels(Request $request, EntityManagerInterface $em, ZplPrinting $zpl)
    {
        $this->denyAccessUnlessGranted('ROLE_PRINT_TUBE_LABELS');

        $form = $this->createFormBuilder()
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
            ->add('numToPrint', IntegerType::class, [
                'label' => 'Number of Labels',
                'data' => 1,
                'attr' => [
                    'min' => 1,
                    'max' => 2000, // todo: max # per roll? reasonable batch size?
                ],
            ])
            ->add('send', SubmitType::class, [
                'label' => 'Print',
                'attr' => ['class' => 'btn-primary'],
            ])
            ->getForm();

        $form->handleRequest($request);

        $printer = null;
        $numToPrint = null;
        $displaySuccessfulPrintAlert = false;
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $printer = $em->getRepository(LabelPrinter::class)->find($data['printer']);
            $builderClass = $data['labelType'];
            $numToPrint = $data['numToPrint'];

            $tubes = [];
            for ($i = 1; $i <= $numToPrint; $i++) {
                $tube = new Tube();
                $em->persist($tube);

                $tubes[] = $tube;
            }

            // Assigns Tube IDs
            $em->flush();

            // Print out the saved tubes
            $builder = new $builderClass();
            $builder->setPrinter($printer);

            foreach ($tubes as $tube) {
                $builder->setTube($tube);
                $zpl->printBuilder($builder);
                $tube->markPrinted();

                $em->flush();
            }

            $displaySuccessfulPrintAlert = true;
        }

        return $this->render('label-printer/print-tube-labels.html.twig', [
            'form' => $form->createView(),
            'previousSelectedPrinter' => $printer,
            'previousNumToPrint' => $numToPrint,
            'displaySuccessfulPrintAlert' => $displaySuccessfulPrintAlert,
        ]);
    }

    /**
     * @Route(path="/", methods={"GET"})
     */
    public function list()
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $printers = $this->getDoctrine()->getRepository(LabelPrinter::class)->findAll();

        return $this->render('label-printer/label-printer-list.html.twig', [
            'printers' => $printers,
        ]);
    }

    /**
     * @Route(path="/new", methods={"GET", "POST"})
     */
    public function new(Request $request) : Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $printer = new LabelPrinter();

        $form = $this->createForm(LabelPrinterType::class, $printer);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $printer = $form->getData();

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($printer);
            $entityManager->flush();

            return $this->redirectToRoute('app_labelprinter_list');
        }

        return $this->render('label-printer/label-printer-form.html.twig', ['new' => true, 'form'=>$form->createView()]);
    }

    /**
     * @Route("/{id<\d+>}", methods={"GET", "POST"})
     */
    public function update(int $id, Request $request) : Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $printer = $this->getDoctrine()->getRepository(LabelPrinter::class)->find($id);
        
        $form = $this->createForm(LabelPrinterType::class, $printer);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->flush();

            return $this->redirectToRoute('app_labelprinter_list');
        }

        return $this->render('label-printer/label-printer-form.html.twig', [
            'new' => false,
            'form'=>$form->createView(),
            'printer'=>$printer,
        ]);
    }

    /**
     * @Route("/{id<\d+>}/info", methods={"GET"}, name="app_label_printer_info")
     */
    public function info(int $id)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $printerEntity = $this->getDoctrine()->getRepository(LabelPrinter::class)->find($id);

        $plainStatus = [];

        try {
            $zplPrinter = new Printer($printerEntity->getHost());
            $status = $zplPrinter->getStatus();

            $plainStatus = [
                'Baud Rate'             => $status->getBaudRate(),
                'Paused'                => $status->isPaused(),
                'Labels Remaining'      => $status->getLabelsRemainingCount(),
                'Paper Out'             => $status->isPaperOut(),
                'Buffer Full'           => $status->isBufferFull(),
                'Ribbon Out'            => $status->isRibbonOut(),
                'Over Temp'             => $status->isOverTemperature(),
                'Under Temp'            => $status->isUnderTemperature(),
            ];
        } catch (\Exception $e) {
            $plainStatus = [
                'isError' => true,
                'code' => $e->getCode(),
                'message' => $e->getMessage()
            ];
        }

        return new JsonResponse($plainStatus);
    }

    /**
     * @Route("/generic", methods={"GET", "POST"})
     */
    public function genericPrint(Request $request, ZplPrinting $zpl)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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
                    'Generic Text: Yellow 1" x 2.5"' => GenericTextLabelBuilder::class,
                ],
                'placeholder' => '- Select -',
                'required' => true,
            ])
            ->add('text', TextType::class, [
                'label' => 'Text',
                'required' => true,
            ])
            ->add('copies', IntegerType::class, [
                'label' => 'Number of Labels',
                'data' => 1,
                'required' => true,
            ])
            ->add('send', SubmitType::class, [
                'label' => 'Print',
                'attr' => ['class' => 'btn-primary'],
            ])
            ->getForm();

        $form->handleRequest($request);

        $b64Image = null;
        $zplText = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $printer = $this->getDoctrine()->getRepository(LabelPrinter::class)->find($data['printer']);
            $builderClass = $data['labelType'];

            $labelBuilder = new $builderClass($printer);
            $labelBuilder->setText($data['text']);

            $zpl->printBuilder($labelBuilder, $data['copies']);

            $result = $zpl->getLastPrinterResponse();

            if($result->getPrinterType() === 'image') {
                /** @var ZplImage $data */
                $data = $result->getData();
                $image = file_get_contents($data->serverPath);
                $b64Image = base64_encode($image);
            } else if ($result->getPrinterType() === 'text') {
                $zplText = $result->getData();
            }
        }

        return $this->render('label-printer/label-printer-generic-print.html.twig', [
            'form' => $form->createView(),
            'b64_image' => $b64Image,
            'zpl_text' => $zplText,
        ]);
    }

    /**
     * @Route("/test", methods={"GET", "POST"})
     */
    public function testPrint(Request $request, ZplPrinting $zpl)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createFormBuilder()
            ->add('tube', EntityType::class, [
                'class' => Tube::class,
                'choice_name' => 'accessionId',
                'required' => true,
                'empty_data' => "",
                'placeholder' => '- None -',
            ])
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
            ->add('send', SubmitType::class, [
                'label' => 'Print',
                'attr' => ['class' => 'btn-primary'],
            ])
            ->getForm();

        $form->handleRequest($request);

        $b64Image = null;
        $zplText = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $printer = $this->getDoctrine()->getRepository(LabelPrinter::class)->find($data['printer']);
            $tube = $this->getDoctrine()->getRepository(Tube::class)->find($data['tube']);
            $builderClass = $data['labelType'];

            $labelBuilder = new $builderClass($printer);
            $labelBuilder->setTube($tube);

            $zpl->printBuilder($labelBuilder);

            $result = $zpl->getLastPrinterResponse();

            if($result->getPrinterType() === 'image') {
                /** @var ZplImage $data */
                $data = $result->getData();
                $image = file_get_contents($data->serverPath);
                $b64Image = base64_encode($image);
            } else if ($result->getPrinterType() === 'text') {
                $zplText = $result->getData();
            }
        }

        return $this->render('label-printer/label-printer-test-print.html.twig', [
            'form' => $form->createView(),
            'b64_image' => $b64Image,
            'zpl_text' => $zplText,
        ]);
    }
}
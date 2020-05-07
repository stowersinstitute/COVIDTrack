<?php


namespace App\Controller;


use App\Entity\LabelPrinter;
use App\Entity\Specimen;
use App\Form\LabelPrinterType;
use App\Label\SpecimenIntakeLabelBuilder;
use App\Label\ZplImage;
use App\Label\ZplPrinterResponse;
use App\Label\ZplPrinting;
use App\Label\ZplTextPrinter;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class LabelPrinterController
 * @package App\Controller
 *
 * @Route(path="/label-printers")
 */
class LabelPrinterController extends AbstractController
{

    /**
     * @Route("/print-specimen-labels", name="app_label_printer_print_specimen_labels")
     */
    public function printSpecimenLabels(Request $request, ZplPrinting $zpl)
    {
        $form = $this->createFormBuilder()
            ->add('printer', EntityType::class, [
                'class' => LabelPrinter::class,
                'choice_name' => 'title',
                'required' => true,
                'empty_data' => "",
                'placeholder' => '- None -'
            ])
            ->add('numToPrint', IntegerType::class, [
                'label' => 'Number of Labels',
                'data' => 1,
                'attr' => [
                    'min' => 1,
                    'max' => 100, // todo: max # per roll? reasonable batch size?
                ],
            ])
            ->add('send', SubmitType::class, [
                'label' => 'Print',
            ])
            ->getForm();

        $form->handleRequest($request);

        $b64Image = null;
        $zplText = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $printer = $this->getDoctrine()->getRepository(LabelPrinter::class)->find($data['printer']);

            // todo: rest of the owl
        }

        return $this->render('label-printer/print-specimen-labels.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route(path="/", methods={"GET"})
     */
    public function list()
    {
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
     * @Route("/test", methods={"GET", "POST"})
     */
    public function testPrint(Request $request, ZplPrinting $zpl)
    {
        $form = $this->createFormBuilder()
            ->add('specimen', EntityType::class, [
                'class' => Specimen::class,
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
            ->add('send', SubmitType::class)
            ->getForm();

        $form->handleRequest($request);

        $b64Image = null;
        $zplText = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $printer = $this->getDoctrine()->getRepository(LabelPrinter::class)->find($data['printer']);
            $specimen = $this->getDoctrine()->getRepository(Specimen::class)->find($data['specimen']);

            $labelBuilder = new SpecimenIntakeLabelBuilder($printer);
            $labelBuilder->setParticipantGroup($specimen->getParticipantGroup());
            $labelBuilder->setSpecimen($specimen);
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
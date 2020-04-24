<?php


namespace App\Controller;


use App\Entity\LabelPrinter;
use App\Form\LabelPrinterType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class SampleController
 * @package App\Controller
 *
 * @Route(path="/label-printer")
 */
class LabelPrinterController extends AbstractController
{

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
     * @Route("/{id}", methods={"GET", "POST"})
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

}
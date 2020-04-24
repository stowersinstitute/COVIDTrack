<?php


namespace App\Controller;


use App\Entity\Sample;
use App\Form\SampleType;
use Gedmo\Loggable\Entity\LogEntry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class SampleController
 * @package App\Controller
 *
 * @Route(path="/samples")
 */
class SampleController extends AbstractController
{

    /**
     * @Route(path="/", methods={"GET"})
     */
    public function list()
    {
        $samples = $this->getDoctrine()->getRepository(Sample::class)->findAll();

        return $this->render('sample/sample-list.html.twig', [
            'samples' => $samples,
        ]);
    }

    /**
     * @Route(path="/new", methods={"GET", "POST"})
     */
    public function new(Request $request) : Response
    {
        $sample = new Sample();

        $form = $this->createForm(SampleType::class, $sample);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sample = $form->getData();

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($sample);
            $entityManager->flush();

            return $this->redirectToRoute('app_sample_list');
        }

        return $this->render('sample/sample-form.html.twig', ['new' => true, 'form'=>$form->createView()]);
    }

    /**
     * @Route("/{id}", methods={"GET", "POST"})
     */
    public function update(int $id, Request $request) : Response
    {
        $sample = $this->getDoctrine()->getRepository(Sample::class)->find($id);
        
        $form = $this->createForm(SampleType::class, $sample);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->flush();

            return $this->redirectToRoute('app_sample_list');
        }

        $revisions = $this->getDoctrine()->getRepository(LogEntry::class)->getLogEntries($sample);

        return $this->render('sample/sample-form.html.twig', [
            'new' => false,
            'form'=>$form->createView(),
            'sample'=>$sample,
            'revisions'=>$revisions
        ]);
    }

}
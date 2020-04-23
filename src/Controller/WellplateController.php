<?php


namespace App\Controller;


use App\Entity\Wellplate;
use App\Form\WellplateType;
use Gedmo\Loggable\Entity\LogEntry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class WellplateController
 * @package App\Controller
 *
 * @Route(path="/wellplates")
 */
class WellplateController extends AbstractController
{

    /**
     * @Route(path="/", methods={"GET"})
     */
    public function list()
    {
        $wellplates = $this->getDoctrine()->getRepository(Wellplate::class)->findAll();

        return $this->render('wellplate/wellplate-list.html.twig', [
            'headers' => ['ID', 'Title'],
            'wellplates' => $wellplates,
        ]);
    }

    /**
     * @Route(path="/new", methods={"GET", "POST"})
     */
    public function new(Request $request) : Response
    {
        $wellplate = new Wellplate();

        $form = $this->createForm(WellplateType::class, $wellplate);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $wellplate = $form->getData();

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($wellplate);
            $entityManager->flush();

            return $this->redirectToRoute('app_wellplate_list');
        }

        return $this->render('wellplate/wellplate-form.html.twig', ['new' => true, 'form'=>$form->createView()]);
    }

    /**
     * @Route("/{id}", methods={"GET", "POST"})
     */
    public function update(int $id, Request $request) : Response
    {
        $wellplate = $this->getDoctrine()->getRepository(Wellplate::class)->find($id);
        
        $form = $this->createForm(WellplateType::class, $wellplate);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->flush();

            return $this->redirectToRoute('app_wellplate_list');
        }

        $revisions = $this->getDoctrine()->getRepository(LogEntry::class)->getLogEntries($wellplate);

        return $this->render('wellplate/wellplate-form.html.twig', [
            'new' => false,
            'form'=>$form->createView(),
            'wellplate'=>$wellplate,
            'revisions'=>$revisions
        ]);
    }

}
<?php

namespace App\Controller;

use App\Entity\LabelPrinter;
use App\Entity\Tube;
use App\Label\SpecimenIntakeLabelBuilder;
use App\Label\ZplPrinting;
use App\Tecan\CannotReadOutputFileException;
use App\Tecan\SpecimenIdNotFoundException;
use App\Tecan\TecanOutput;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
}

<?php


namespace App\Controller;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Entity\DropOff;
use App\Entity\ParticipantGroup;
use App\Entity\Tube;
use App\Form\TubeType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/kiosk")
 */
class KioskController extends AbstractController
{
    /**
     * @var SpecimenAccessionIdGenerator
     */
    private $specimenIdGen;

    public function __construct(SpecimenAccessionIdGenerator $specimenIdGen)
    {
        $this->specimenIdGen = $specimenIdGen;
    }

    /**
     * @Route(path="/", name="kiosk_index", methods={"GET", "POST"})
     */
    public function index(Request $request)
    {
        $dropOff = new DropOff();

        $form = $this->createFormBuilder($dropOff)
            ->add('group', EntityType::class, [
                'class' => ParticipantGroup::class,
                'choice_name' => 'title',
                'required' => false,
                'empty_data' => "",
                'placeholder' => '- None -',
                'attr' => ['class' => 'input-lg'],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Next >',
                'attr' => ['class' => 'btn-primary'],
            ])
            ->getForm();

        $dropOff = $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var DropOff $dropOff */
            $dropOff = $form->getData();

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($dropOff);
            $entityManager->flush();

            return $this->redirectToRoute('app_kiosk_tubeinput', ['id' => $dropOff->getId()]);
        }

        return $this->render('kiosk/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route(path="/{id}/add-tube", methods={"GET", "POST"})
     */
    public function tubeInput(int $id, Request $request)
    {
        $dropOff = $this->getDoctrine()->getRepository(DropOff::class)->find($id);

        $form = $this->createForm(TubeType::class);

        $form = $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();

            $formData = $form->getData();

            /** @var Tube $tube */
            $tube = $this->getDoctrine()
                ->getRepository(Tube::class)
                ->findOneByAnyId($formData['accessionId']);
            if (!$tube) {
                // TODO: Need a user-friendly error
                throw new \InvalidArgumentException('Tube ID does not exist');
            }

            $collectedAt = new \DateTime($formData['collectedAtDate'] . $formData['collectedAtTime']);

            // Also creates Specimen
            $tube->kioskDropoff($this->specimenIdGen, $dropOff, $dropOff->getGroup(), $formData['tubeType'], $collectedAt);

            if($form->get('done')->isClicked()) {
                print_r("was clicked");
                $dropOff->markCompleted();
            }

            $entityManager->flush();

            if ($form->get('save')->isClicked()) {
                return $this->redirectToRoute('app_kiosk_tubeinput', ['id' => $dropOff->getId()]);
            } else if($form->get('done')->isClicked()) {
                return $this->redirectToRoute('app_kiosk_completedropoff', ['id' => $dropOff->getId()]);
            }
        }

        return $this->render('kiosk/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route(path="/{id}/complete", methods={"GET"})
     */
    public function completeDropOff(int $id, Request $request)
    {
        return $this->render('kiosk/complete.html.twig');
    }
}
<?php


namespace App\Controller;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Entity\DropOff;
use App\Entity\Kiosk;
use App\Entity\ParticipantGroup;
use App\Entity\Tube;
use App\Form\TubeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
     * NOTE: Kiosks are selected by tapping on a form submit button which results in
     *  this endpoint receiving a POST request and a kioskId parameter. No Symfony
     *  form used since it's so simple.
     *
     * @Route(path="/provision", name="kiosk_provision", methods={"GET", "POST"})
     */
    public function provision(Request $request, EntityManagerInterface $em)
    {
        $kioskRepo = $em->getRepository(Kiosk::class);

        // If there's a kiosk ID being posted then the form was submitted
        if ($request->getMethod() === Request::METHOD_POST) {
            $kiosk = $kioskRepo->find($request->get('kioskId'));
            if (!$kiosk) throw new \InvalidArgumentException('Invalid Kiosk ID');

            // Verify it wasn't already provisioned
            if ($kiosk->isProvisioned()) throw new \LogicException('This kiosk is already provisioned');

            $kiosk->setIsProvisioned(true);
            $em->flush();

            $response = $this->redirectToRoute('kiosk_index');
            // Tag the client with a cookie to track the associated kiosk ID
            $response->headers->setCookie(new Cookie('CT_KIOSK_ID', $kiosk->getId(), new \DateTimeImmutable('2038-01-01')));

            return $response;
        }

        $unprovisionedKiosks = $kioskRepo->findUnprovisioned();

        return $this->render('kiosk/provision.html.twig', [
            'unprovisionedKiosks' => $unprovisionedKiosks,
        ]);
    }

    /**
     * @Route(path="/", name="kiosk_index", methods={"GET", "POST"})
     */
    public function index(Request $request, EntityManagerInterface $em)
    {
        $this->mustHavePermissions();

        if ($this->needsToBeProvisioned($request, $em)) return $this->redirectToRoute('kiosk_provision');

        $dropOff = new DropOff();

        $form = $this->createFormBuilder($dropOff)
            ->add('group', EntityType::class, [
                'class' => ParticipantGroup::class,
                'choice_name' => 'title',
                'required' => true,
                'empty_data' => "",
                'placeholder' => '- None -',
                'attr' => ['class' => 'input-lg', 'data-scanner-input' => null],
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
            'kiosk_state' => Kiosk::STATE_WAITING_DROPOFF_START,
        ]);
    }

    /**
     * @Route(path="/{id}/add-tube", methods={"GET", "POST"})
     */
    public function tubeInput(int $id, Request $request, EntityManagerInterface $em)
    {
        $this->mustHavePermissions();

        if ($this->needsToBeProvisioned($request, $em)) return $this->redirectToRoute('kiosk_provision');

        /** @var DropOff $dropOff */
        $dropOff = $this->getDoctrine()->getRepository(DropOff::class)->find($id);

        if (!$dropOff) {
            throw new NotFoundHttpException('Drop off not found');
        }

        // This is separate because it shows up in a different part of the template. Is there a better way?
        $cancelForm = $this->createFormBuilder()
            ->add('cancel', SubmitType::class, [
                'label' => 'Cancel Drop Off',
                'attr' => ['class' => 'btn-sm btn-danger'],
                'validate' => false,
            ])
            ->getForm();

        $cancelForm = $cancelForm->handleRequest($request);

        // If we're cancelling, we don't need to validate the form.
        if ($cancelForm->get('cancel')->isClicked()) {
            $dropOff->cancel();
            $em->remove($dropOff);
            $em->flush();
            return $this->redirectToRoute('app_kiosk_canceldropoff', ['id' => $dropOff->getId()]);
        }

        // The real form
        $form = $this->createForm(TubeType::class);

        $form = $form->handleRequest($request);


        if ($form->isSubmitted() && $form->isValid()) {
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

            $tube->kioskDropoff($dropOff, $dropOff->getGroup(), $formData['tubeType'], $collectedAt);

            if ($form->get('done')->isClicked()) {
                // Also creates Specimen
                $dropOff->markCompleted($this->specimenIdGen);
            }

            $em->flush();

            if ($form->get('save')->isClicked()) {
                return $this->redirectToRoute('app_kiosk_tubeinput', ['id' => $dropOff->getId()]);
            } else if ($form->get('done')->isClicked()) {
                return $this->redirectToRoute('app_kiosk_completedropoff', ['id' => $dropOff->getId()]);
            }
        }

        return $this->render('kiosk/tube-input.html.twig', [
            'form' => $form->createView(),
            'cancelForm' => $cancelForm->createView(),
            'kiosk_state' => Kiosk::STATE_TUBE_INPUT,
        ]);
    }

    /**
     * @Route(path="/{id}/complete", methods={"GET"})
     */
    public function completeDropOff(int $id, Request $request, EntityManagerInterface $em)
    {
        $this->mustHavePermissions();

        if ($this->needsToBeProvisioned($request, $em)) return $this->redirectToRoute('kiosk_provision');

        return $this->render('kiosk/complete.html.twig');
    }

    /**
     * @Route(path="/cancel", methods={"GET"})
     */
    public function cancelDropOff()
    {
        $this->mustHavePermissions();

        return $this->render('kiosk/cancel.html.twig');
    }

    /**
     * @Route(path="/heartbeat", methods={"POST"}, name="kiosk_heartbeat")
     */
    public function heartbeat(Request $request, EntityManagerInterface $em)
    {
        $kioskId = $request->cookies->get('CT_KIOSK_ID');
        if (!$kioskId) return new JsonResponse(['isError' => true, 'message' => 'CT_KIOSK_ID cookie not present']);

        $kiosk = $em->getRepository(Kiosk::class)->find($kioskId);
        if (!$kiosk) return new JsonResponse(['isError' => true, 'message' => 'Invalid kiosk ID']);
        if (!$kiosk->isProvisioned()) return new JsonResponse(['isError' => true, 'message' => 'Tried to heartbeat on an unprovisioned kiosk']);

        // Update kiosk properties
        $kiosk->setLastHeartbeatAt(new \DateTimeImmutable());
        $kiosk->setLastHeartbeatIdleSeconds($request->get('idleSeconds'));
        $kiosk->setLastHeartbeatState($request->get('state'));
        $kiosk->setLastHeartbeatVersionId($request->get('appVersion'));
        $kiosk->setLastHeartbeatIp($request->getClientIp());

        $em->flush();

        return new JsonResponse([
            'appVersion' => $this->getParameter('app_current_version'),
        ]);
    }

    private function needsToBeProvisioned(Request $request, EntityManagerInterface $em)
    {
        // Needs provisioning if there's no ID cookie
        $kioskId = $request->cookies->get('CT_KIOSK_ID');
        if (!$kioskId) return true;

        // Needs provisioning if kiosk ID is no longer valid
        $kiosk = $em->find(Kiosk::class, $kioskId);
        if (!$kiosk) return true;

        // May explicitly require provisioning
        if (!$kiosk->isProvisioned()) return true;

        return false;
    }

    protected function mustHavePermissions()
    {
        $this->denyAccessUnlessGranted('ROLE_KIOSK_UI', 'Kiosk Access Required', 'You must have kiosk UI permissions to view this page');
    }
}
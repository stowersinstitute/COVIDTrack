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
use Symfony\Component\Routing\RouterInterface;

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
     * Begin checkin process by selecting a Participant Group.
     *
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
                'label' => 'Continue >',
                'attr' => ['class' => 'btn-sm btn-success'],
            ])
            ->getForm();

        $dropOff = $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var DropOff $dropOff */
            $dropOff = $form->getData();

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($dropOff);
            $entityManager->flush();

            return $this->redirectToRoute('kiosk_add_tube', ['id' => $dropOff->getId()]);
        }

        return $this->render('kiosk/index.html.twig', [
            'form' => $form->createView(),
            'kiosk_state' => Kiosk::STATE_WAITING_DROPOFF_START,
        ]);
    }

    /**
     * Add Tube for check-in.
     *
     * @Route(path="/{id}/add-tube", methods={"GET", "POST"}, name="kiosk_add_tube")
     */
    public function tubeInput(int $id, Request $request, EntityManagerInterface $em)
    {
        $this->mustHavePermissions();

        if ($this->needsToBeProvisioned($request, $em)) return $this->redirectToRoute('kiosk_provision');

        /** @var DropOff $dropOff */
        $dropOff = $this->mustFindDropoff($id);

        $form = $this->createForm(TubeType::class);
        $form->handleRequest($request);
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

            $em->flush();

            if ($form->get('save')->isClicked()) {
                return $this->redirectToRoute('kiosk_add_tube', ['id' => $dropOff->getId()]);
            } else if ($form->get('review')->isClicked()) {
                return $this->redirectToRoute('kiosk_review', ['id' => $dropOff->getId()]);
            }
        }

        return $this->render('kiosk/tube-input.html.twig', [
            'form' => $form->createView(),
            'dropoff' => $dropOff,
            'group' => $dropOff->getGroup(),
            'kiosk_state' => Kiosk::STATE_TUBE_INPUT,
        ]);
    }

    /**
     * View previously added Tubes to verify before completion.
     * POST back to this route to complete check-in.
     *
     * @Route(path="/{id}/review", methods={"GET", "POST"}, name="kiosk_review")
     */
    public function review(int $id, Request $request, EntityManagerInterface $em)
    {
        $this->mustHavePermissions();

        $dropOff = $this->mustFindDropoff($id);

        $form = $this->createFormBuilder()
            ->add('finish', SubmitType::class, [
                'label' => 'Finish >',
                'attr' => ['class' => 'btn btn-success'],
            ])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Also creates Specimen
            $dropOff->markCompleted($this->specimenIdGen);

            $em->flush();

            return $this->redirectToRoute('kiosk_complete');
        }

        return $this->render('kiosk/review.html.twig', [
            'dropoff' => $dropOff,
            'group' => $dropOff->getGroup(),
            'tubes' => $dropOff->getTubes(),
            'finishButtonForm' => $form->createView(),
        ]);
    }

    /**
     * Drop Off process complete.
     *
     * @Route(path="/complete", methods={"GET"}, name="kiosk_complete")
     */
    public function complete(Request $request, EntityManagerInterface $em)
    {
        $this->mustHavePermissions();

        if ($this->needsToBeProvisioned($request, $em)) return $this->redirectToRoute('kiosk_provision');

        return $this->render('kiosk/complete.html.twig');
    }

    /**
     * Cancel the Drop Off process.
     *
     * @Route(path="/{id<\d+>}/cancel", methods={"POST"}, name="kiosk_cancel")
     */
    public function cancel(int $id, Request $request, EntityManagerInterface $em, RouterInterface $router)
    {
        $this->mustHavePermissions();

        $dropOff = $this->mustFindDropoff($id);

        $dropOff->cancel();

        $em->remove($dropOff);
        $em->flush();

        return new JsonResponse([
            'redirectToUrl' => $router->generate('kiosk_cancel_complete'),
        ]);
    }

    /**
     * Completion screen after canceling.
     *
     * @Route(path="/canceled", methods={"GET"}, name="kiosk_cancel_complete")
     */
    public function cancelComplete(Request $request, EntityManagerInterface $em)
    {
        if ($this->needsToBeProvisioned($request, $em)) {
            return $this->redirectToRoute('kiosk_provision');
        }

        return $this->render('kiosk/cancel-complete.html.twig');
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

    private function mustFindDropoff(int $id): Dropoff
    {
        /** @var DropOff $drop */
        $drop = $this->getDoctrine()->getRepository(DropOff::class)->find($id);

        if (!$drop) {
            throw new NotFoundHttpException('Drop off session not found');
        }

        return $drop;
    }
}
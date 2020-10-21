<?php


namespace App\Controller;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Configuration\AppConfiguration;
use App\Entity\Kiosk;
use App\Entity\KioskSession;
use App\Entity\KioskSessionTube;
use App\Entity\ParticipantGroup;
use App\Entity\Tube;
use App\Form\KioskAddTubeForm;
use App\Form\Type\TextLookupType;
use App\Util\EntityUtils;
use Doctrine\ORM\EntityManagerInterface;
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
    const KIOSK_COOKIE_KEY = 'CT_KIOSK_ID';

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
            $response->headers->setCookie(new Cookie(self::KIOSK_COOKIE_KEY, $kiosk->getId(), new \DateTimeImmutable('2038-01-01')));

            return $response;
        }

        $unprovisionedKiosks = $kioskRepo->findUnprovisioned();

        return $this->render('kiosk/provision.html.twig', [
            'unprovisionedKiosks' => $unprovisionedKiosks,
        ]);
    }

    /**
     * Begin Kiosk interaction by selecting a Participant Group.
     *
     * @Route(path="/", name="kiosk_index", methods={"GET", "POST"})
     */
    public function index(Request $request, EntityManagerInterface $em)
    {
        $this->mustHavePermissions();

        if ($this->needsToBeProvisioned($request, $em)) return $this->redirectToRoute('kiosk_provision');

        $kiosk = $this->mustFindKiosk($request);
        $kioskSession = new KioskSession($kiosk);

        $form = $this->createFormBuilder()
            ->add('participantGroupTitle', TextLookupType::class, [
                'label' => 'Group/Individual ID',
                'attr' => ['class' => 'input-lg', 'data-scanner-input' => null],
                'button_text' => 'Lookup',
            ])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $group = $em->getRepository(ParticipantGroup::class)->findOneBy(['title' => $data['participantGroupTitle']]);

            $kioskSession->setParticipantGroup($group);

            $em->persist($kioskSession);
            $em->flush();

            return $this->redirectToRoute('kiosk_add_tube', ['id' => $kioskSession->getId()]);
        }

        return $this->render('kiosk/index.html.twig', [
            'form' => $form->createView(),
            'kiosk_state' => Kiosk::STATE_WAITING_DROPOFF_START,
        ]);
    }

    /**
     * Add Tube that Participant is returning.
     *
     * @Route(path="/{id<\d+>}/add-tube", methods={"GET", "POST"}, name="kiosk_add_tube")
     */
    public function tubeInput(int $id, Request $request, EntityManagerInterface $em, AppConfiguration $appConfig)
    {
        $this->mustHavePermissions();

        if ($this->needsToBeProvisioned($request, $em)) return $this->redirectToRoute('kiosk_provision');

        $kiosk = $this->mustFindKiosk($request);
        $kioskSession = $this->mustFindKioskSession($id);
        if (!$this->usesSameKiosk($kioskSession, $kiosk)) {
            return $this->redirectToRoute('kiosk_index');
        }

        $collectionTimeExperience = $appConfig->get(ConfigController::TUBE_COLLECTION_TIME_EXPERIENCE);
        $form = $this->createForm(KioskAddTubeForm::class, null, [
            'participantGroup' => $kioskSession->getParticipantGroup(),
            'minCollectionTimeHour' => (int)$appConfig->get(ConfigController::TUBE_COLLECTED_AT_START),
            'maxCollectionTimeHour' => (int)$appConfig->get(ConfigController::TUBE_COLLECTED_AT_END),
            'collectionTimeExperience' => $collectionTimeExperience,
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();

            /** @var Tube $tube */
            $tube = $this->getDoctrine()
                ->getRepository(Tube::class)
                ->findOneByAccessionId($formData['accessionId']);
            if (!$tube) {
                // TODO: Need a user-friendly error
                throw new \InvalidArgumentException('Tube ID does not exist');
            }

            $tubeType = $formData['tubeType'];

            if ($collectionTimeExperience === ConfigController::TUBE_COLLECTION_TIME_OPTION_AUTO) {
                $collectedAt = new \DateTimeImmutable('now');
            } else {
                $collectedAt = new \DateTimeImmutable($formData['collectedAtDate'] . $formData['collectedAtTime']);
            }

            $sessionTube = new KioskSessionTube($kioskSession, $tube, $tubeType, $collectedAt);
            $kioskSession->addTubeData($sessionTube);

            $kioskSession->setMostRecentScreen(KioskSession::SCREEN_ADD_TUBES);

            $em->persist($sessionTube);
            $em->flush();

            return $this->redirectToRoute('kiosk_review', ['id' => $kioskSession->getId()]);
        }

        return $this->render('kiosk/tube-input.html.twig', [
            'form' => $form->createView(),
            'kioskSession' => $kioskSession,
            'kiosk_state' => Kiosk::STATE_TUBE_INPUT
        ]);
    }

    /**
     * Checks if the given group ID is available for checkin
     *
     * Required POST params:
     * - participantGroupTitle (string) Participant Group Title to check
     *
     * @Route(path="/group-available-check", methods={"POST"}, name="kiosk_group_available_check")
     */
    public function groupAvailableCheck(Request $request, EntityManagerInterface $em)
    {
        $this->mustHavePermissions();
        $this->mustFindKiosk($request);

        $groupTitle = $request->get('participantGroupTitle');

        $group = $em->getRepository(ParticipantGroup::class)->findOneBy(['title' => $groupTitle]);

        if (!$group) {
            return new JsonResponse([
                'isError' => true,
                'message' => "Unknown ID. Please contact staff for assistance.",
            ]);
        }

        if (!$group->isActive()) {
            return new JsonResponse([
                'isError' => true,
                'message' => "Inactive ID. Please contact staff for assistance.",
            ]);
        }

        if (!$group->acceptsBloodSpecimens() && !$group->acceptsSalivaSpecimens()) {
            return new JsonResponse([
                'isError' => true,
                'message' => "No active sample drop off types for this ID. Please contact staff for assistance.",
            ]);
        }

         return new JsonResponse([
            'result' => true,
        ]);
    }

    /**
     * Checks if the given tube is available for checkin
     *
     * @Route(path="/tube-available-check", methods={"POST"}, name="kiosk_tube_available_check")
     */
    public function tubeAvailableCheck(Request $request, EntityManagerInterface $em)
    {
        $this->mustHavePermissions();
        $this->mustFindKiosk($request);

        $accessionId = $request->get('accessionId');

        $tube = $em->getRepository(Tube::class)->findOneByAccessionId($accessionId);

        if (!$tube || !$tube->willAllowDropOff()) {
            return new JsonResponse([
                'isError' => true,
                'message' => "This tube is unavailable for drop-off. Please contact staff for assistance.",
            ]);
        }

        return new JsonResponse([
            'result' => true,
        ]);
    }

    /**
     * View previously added Tubes to verify before completion.
     * POST back to this route to complete Kiosk interaction.
     *
     * @Route(path="/{id<\d+>}/review", methods={"GET", "POST"}, name="kiosk_review")
     */
    public function review(int $id, Request $request, EntityManagerInterface $em)
    {
        $this->mustHavePermissions();

        $kiosk = $this->mustFindKiosk($request);
        $kioskSession = $this->mustFindKioskSession($id);
        if (!$this->usesSameKiosk($kioskSession, $kiosk)) {
            return $this->redirectToRoute('kiosk_index');
        }

        $form = $this->createFormBuilder()
            ->add('finish', SubmitType::class, [
                'label' => 'Finish >',
                'attr' => ['class' => 'btn btn-success btn-lg'],
            ])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $kioskSession->finish($this->specimenIdGen);

            $em->flush();

            return $this->redirectToRoute('kiosk_complete');
        }

        return $this->render('kiosk/review.html.twig', [
            'kioskSession' => $kioskSession,
            'tubeData' => $kioskSession->getTubeData(),
            'finishButtonForm' => $form->createView(),
        ]);
    }

    /**
     * Completion screen after completing kiosk interaction.
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
     * Cancel this Kiosk interaction.
     *
     * @Route(path="/{id<\d+>}/cancel", methods={"POST"}, name="kiosk_cancel")
     */
    public function cancel(int $id, Request $request, EntityManagerInterface $em, RouterInterface $router)
    {
        $this->mustHavePermissions();

        $kiosk = $this->mustFindKiosk($request);
        $kioskSession = $this->mustFindKioskSession($id);
        if (!$this->usesSameKiosk($kioskSession, $kiosk)) {
            return $this->redirectToRoute('kiosk_index');
        }

        $kioskSession->cancel();

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
        $kiosk = $this->getKioskIdFromRequestCookie($request);
        if (!$kiosk) {
            new JsonResponse([
                'isError' => true,
                'message' => self::KIOSK_COOKIE_KEY . ' cookie not present',
            ]);
        }

        $kioskId = $request->cookies->get(self::KIOSK_COOKIE_KEY);
        if (!$kioskId) return new JsonResponse(['isError' => true, 'message' => 'CT_KIOSK_ID cookie not present']);


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

    /**
     * @Route(path="/{id<\d+>}/expire", methods={"POST"}, name="kiosk_expire")
     */
    public function expire(int $id, Request $request, EntityManagerInterface $em, RouterInterface $router)
    {
        $this->mustHavePermissions();

        $kiosk = $this->mustFindKiosk($request);
        $kioskSession = $this->mustFindKioskSession($id);
        if (!$this->usesSameKiosk($kioskSession, $kiosk)) {
            return new JsonResponse([
                'redirectToUrl' => $router->generate('kiosk_index'),
            ]);
        }

        // If we have tube data then this session can completed otherwise cancel it.
        if (count($kioskSession->getTubeData()) > 0) {
            $kioskSession->finish($this->specimenIdGen);
        } else {
            $kioskSession->cancel();
        }

        $kioskSession->expire();

        $em->flush();

        return new JsonResponse([
            'redirectToUrl' => $router->generate('kiosk_index'),
        ]);
    }

    private function needsToBeProvisioned(Request $request, EntityManagerInterface $em)
    {
        // Needs provisioning if there's no ID cookie
        $kioskId = $request->cookies->get(self::KIOSK_COOKIE_KEY);
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

    private function mustFindKioskSession(int $id): KioskSession
    {
        /** @var KioskSession $session */
        $session = $this->getDoctrine()->getRepository(KioskSession::class)->find($id);

        if (!$session || $session->isCancelled()) {
            throw new NotFoundHttpException('Kiosk session not found');
        }

        return $session;
    }

    private function getKioskIdFromRequestCookie(Request $request): ?Kiosk
    {
        $kioskId = $request->cookies->get(self::KIOSK_COOKIE_KEY);
        if (!$kioskId) {
            return null;
        }

        return $this->getDoctrine()
            ->getRepository(Kiosk::class)
            ->find($kioskId);
    }

    private function mustFindKiosk(Request $request): Kiosk
    {
        $kioskId = $request->cookies->get(self::KIOSK_COOKIE_KEY);
        if (!$kioskId) {
            throw new \InvalidArgumentException('Cannot find Kiosk using data in Request');
        }

        $kiosk = $this->getDoctrine()
            ->getRepository(Kiosk::class)
            ->find($kioskId);
        if (!$kiosk) {
            throw new \InvalidArgumentException('Cannot find Kiosk');
        }

        return $kiosk;
    }

    private function usesSameKiosk(KioskSession $kioskSession, Kiosk $kiosk): bool
    {
        return EntityUtils::isSameEntity($kioskSession->getKiosk(), $kiosk);
    }
}
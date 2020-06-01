<?php


namespace App\Controller;


use App\Entity\DropOffWindow;
use App\Entity\DropOffSchedule;
use App\Form\DropOffScheduleForm;
use App\Scheduling\ScheduleCalculator;
use App\Util\DateUtils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Report on maintained data.
 *
 * @Route("/drop-off-schedule")
 */
class DropOffScheduleController extends AbstractController
{
    /** @var EntityManagerInterface */
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @Route("/", name="site_drop_off_schedule_index")
     */
    public function index(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $siteSchedule = null;
        $form = $this->createForm(DropOffScheduleForm::class);

        $form->handleRequest($request);

        // Set form defaults
        if (!$form->isSubmitted()) {
            $siteSchedule = $this->getDefaultSiteDropOffSchedule();

            $form->get('startTime')->setData($siteSchedule->getDailyStartTime());
            $form->get('endTime')->setData($siteSchedule->getDailyEndTime());
            $form->get('interval')->setData($siteSchedule->getWindowIntervalMinutes());
            $form->get('numExpectedDropOffsPerGroup')->setData($siteSchedule->getNumExpectedDropOffsPerGroup());

            foreach ($siteSchedule->getDaysOfTheWeek() as $day) {
                $form->get($day . '_enabled')->setData(true);
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $siteSchedule = $this->commitSchedule($form);
        }

        return $this->render('site-drop-off-schedule/index.html.twig', [
            'schedule' => $siteSchedule,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Writes form data to the database
     */
    protected function commitSchedule(FormInterface $form)
    {
        $dropoffSchedule = $this->ensureDropOffSchedule('Default Schedule');

        $enabledDays = [];
        foreach (DropOffScheduleForm::DAYS as $day) { // MO, TU, WE, ...
            // Only process enabled days
            if (!$form->get($day . '_enabled')->getData()) continue;

            $enabledDays[] = $day;
        }

        $dropoffSchedule->setDailyStartTime(DateUtils::toImmutable($form->get('startTime')->getData()));
        $dropoffSchedule->setDailyEndTime(DateUtils::toImmutable($form->get('endTime')->getData()));
        $dropoffSchedule->setWindowIntervalMinutes($form->get('interval')->getData());
        $dropoffSchedule->setNumExpectedDropOffsPerGroup($form->get('numExpectedDropOffsPerGroup')->getData());

        $dropoffSchedule->setDaysOfTheWeek($enabledDays);

        $this->syncDropOffWindows($dropoffSchedule);

        $this->em->flush();

        return $dropoffSchedule;
    }

    protected function syncDropOffWindows(DropOffSchedule $schedule)
    {
        $calculator = new ScheduleCalculator($schedule);
        $newWindows = $calculator->getWeeklyWindows();

        foreach ($newWindows as $window) {
            if ($this->windowIsInDatabase($schedule, $window)) continue;

            // Window is not in the database, it needs to be persisted as a new object
            $this->em->persist($window);
        }
    }

    protected function windowIsInDatabase(DropOffSchedule $schedule, DropOffWindow $window)
    {
        $dbWindows = $schedule->getCommittedDropOffWindows();

        foreach ($dbWindows as $dbWindow) {
            if ($dbWindow->getTimeSlotId() === $window->getTimeSlotId()) return true;
        }

        return false;
    }

    protected function ensureDropOffSchedule(string $label) : DropOffSchedule
    {
        $repo = $this->em->getRepository(DropOffSchedule::class);

        // System only supports one schedule at the moment
        $currentSchedules = $repo->findAll();

        if ($currentSchedules) return $currentSchedules[0];

        $schedule = new DropOffSchedule($label);
        $this->em->persist($schedule);

        return $schedule;
    }

    protected function getDefaultSiteDropOffSchedule() : DropOffSchedule
    {
        return $this->ensureDropOffSchedule('Default Schedule');
    }
}
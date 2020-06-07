<?php


namespace App\Controller;


use App\Entity\DropOffSchedule;
use Doctrine\ORM\EntityManagerInterface;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\ArrayTransformerConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/group-scheduling")
 */
class ParticipantGroupSchedulingController extends AbstractController
{
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @Route("/", name="participant_group_scheduling_index")
     */
    public function index()
    {
        $this->denyAccessUnlessGranted('ROLE_PARTICIPANT_GROUP_SCHEDULE_VIEW');
        $schedule = $this->getDefaultSiteDropOffSchedule();

        $nextDropOffWindowStartsAt = $schedule->getNextDropOffWindowStartsAt();
        $nextWindowParticipantTotals = $schedule->getParticipantTotalsOn($nextDropOffWindowStartsAt);

        return $this->render('participant-group-scheduling/index.html.twig', [
            'nextDropOffWindowStartsAt' => $nextDropOffWindowStartsAt,
            'nextWindowParticipantTotals' => $nextWindowParticipantTotals,
            'schedule' => $schedule,
        ]);
    }

    protected function getDefaultSiteDropOffSchedule() : ?DropOffSchedule
    {
        return $this->em
            ->getRepository(DropOffSchedule::class)
            ->findOneBy([])
        ;
    }
}
<?php


namespace App\Controller;


use App\Entity\DropOffSchedule;
use Doctrine\ORM\EntityManagerInterface;
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
        $schedule = $this->getDefaultSiteDropOffSchedule();

        return $this->render('participant-group-scheduling/index.html.twig', [
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
<?php


namespace App\Controller;

use App\Entity\ParticipantGroup;
use App\Entity\Specimen;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/kiosk")
 */
class KioskController extends AbstractController
{
    /**
     * @Route(path="/", name="kiosk_index", methods={"GET"})
     */
    public function index()
    {
        return $this->render('kiosk/index.html.twig', [

        ]);
    }

    /**
     * @Route(path="/specimen-dropoff/{groupId}/{specimenId}", name="kiosk_specimen_dropoff", methods={"GET"})
     */
    public function recordSpecimenDropoff($groupId, $specimenId)
    {
        $error = '';
        $em = $this->getDoctrine()->getManager();

        /** @var Specimen $specimen */
        $specimen = $em->getRepository(Specimen::class)
            ->findOneBy(['accessionId' => $specimenId]);

        /** @var ParticipantGroup $group */
        $group = $em->getRepository(ParticipantGroup::class)
            ->findOneBy(['accessionId' => $groupId]);
        // Try searching by title
        if (!$group) {
            $group = $em->getRepository(ParticipantGroup::class)
                ->findOneBy(['title' => $groupId]);
        }

        // todo: better handling of multiple errors
        if (!$group) {
            $error .= 'Group not found';
        }
        if (!$specimen) {
            $error .= 'Specimen not found!';
        }

        if (!$error) {
            $specimen->setParticipantGroup($group);
            $specimen->setCollectedAt(new \DateTime());
            $specimen->setStatus(Specimen::STATUS_IN_PROCESS);
        }

        $em->flush();
        return $this->render('kiosk/specimen-dropoff.html.twig', [
            'groupId' => $groupId,
            'specimenId' => $specimenId,
            'error' => $error,
        ]);
    }
}
<?php


namespace App\Controller;


use App\Entity\Kiosk;
use App\Form\KioskType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/kiosk-admin")
 */
class KioskAdminController extends AbstractController
{
    /**
     * @Route(path="/", methods={"GET"}, name="kiosk_admin_list")
     */
    public function list(EntityManagerInterface $em)
    {
        $repo = $em->getRepository(Kiosk::class);

        return $this->render('kiosk-admin/list.html.twig', [
            'kiosks' => $repo->findAll(),
        ]);
    }

    /**
     * @Route(path="/new", methods={"GET", "POST"}, name="kiosk_admin_new")
     */
    public function new(Request $request, EntityManagerInterface $em)
    {
        $kiosk = null; // Will refer to the entity if editing
        $form = $this->createForm(KioskType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $kiosk = new Kiosk($data['label']);
            $kiosk->setLocation($data['location'] ?? null);

            $em->persist($kiosk);
            $em->flush();

            return $this->redirectToRoute('kiosk_admin_list');
        }

        return $this->render('kiosk-admin/form.html.twig', [
            'kiosk' => $kiosk,
            'form'=> $form->createView(),
        ]);
    }

    /**
     * @Route("/{kioskId<\d+>}/edit", methods={"GET", "POST"}, name="kiosk_admin_edit")
     */
    public function edit(int $kioskId, Request $request, EntityManagerInterface $em)
    {
        $kiosk = $em->find(Kiosk::class, $kioskId);

        $form = $this->createForm(KioskType::class, $kiosk);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('kiosk_admin_list', [

            ]);
        }

        return $this->render('kiosk-admin/form.html.twig', [
            'kiosk' => $kiosk,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{kioskId<\d+>}/delete", methods={"POST"}, name="kiosk_admin_delete")
     */
    public function delete(int $kioskId, EntityManagerInterface $em)
    {
        $kiosk = $em->find(Kiosk::class, $kioskId);

        $em->remove($kiosk);

        $em->flush();

        return $this->redirectToRoute('kiosk_admin_list');
    }
}
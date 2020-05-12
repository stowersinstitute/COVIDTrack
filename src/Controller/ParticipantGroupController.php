<?php

namespace App\Controller;

use App\AccessionId\ParticipantGroupAccessionIdGenerator;
use App\Entity\ExcelImportWorkbook;
use App\Entity\AuditLog;
use App\Entity\ParticipantGroup;
use App\ExcelImport\ExcelImporter;
use App\ExcelImport\ParticipantGroupImporter;
use App\Form\GenericExcelImportType;
use App\Form\ParticipantGroupForm;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Interact with Participant Groups.
 *
 * @Route(path="/groups")
 */
class ParticipantGroupController extends AbstractController
{
    /**
     * List all Participant Groups
     *
     * @Route(path="/", methods={"GET"}, name="app_participant_group_list")
     */
    public function list()
    {
        $groupRepo = $this->getDoctrine()->getRepository(ParticipantGroup::class);

        return $this->render('participantGroup/participant-group-list.html.twig', [
            'groups' => $groupRepo->findAll(),
        ]);
    }

    /**
     * Create a single new Group
     *
     * @Route(path="/new", methods={"GET", "POST"}, name="app_participant_group_new")
     */
    public function new(Request $request) : Response
    {
        $form = $this->createForm(ParticipantGroupForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $group = $form->getData();

            $em = $this->getDoctrine()->getManager();
            $em->persist($group);
            $em->flush();

            return $this->redirectToRoute('app_participant_group_list');
        }

        return $this->render('participantGroup/participant-group-form.html.twig', [
            'new' => true,
            'form'=> $form->createView(),
        ]);
    }

    /**
     * Edit a single Group.
     *
     * @Route("/{accessionId}/edit", methods={"GET", "POST"}, name="app_participant_group_edit")
     */
    public function edit(string $accessionId, Request $request) : Response
    {
        $group = $this->findGroup($accessionId);

        $form = $this->createForm(ParticipantGroupForm::class, $group);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->flush();

            return $this->redirectToRoute('app_participant_group_view', [
                'accessionId' => $group->getAccessionId(),
            ]);
        }

        return $this->render('participantGroup/participant-group-form.html.twig', [
            'new' => false,
            'form' => $form->createView(),
            'group' => $group,
        ]);
    }

    /**
     * View a single Group.
     *
     * @Route("/{accessionId}", methods={"GET", "POST"}, name="app_participant_group_view")
     */
    public function view(string $accessionId)
    {
        $group = $this->findGroup($accessionId);

        $auditLogs = $this->getDoctrine()
            ->getRepository(AuditLog::class)
            ->getLogEntries($group);

        return $this->render('participantGroup/participant-group-view.html.twig', [
            'group' => $group,
            'auditLogs' => $auditLogs,
        ]);
    }

    /**
     * @Route("/excel-import/start", name="group_excel_import")
     */
    public function excelImport(Request $request, ExcelImporter $excelImporter)
    {
        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(GenericExcelImportType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $excelFile */
            $excelFile = $form->get('excelFile')->getData();

            $workbook = $excelImporter->createWorkbookFromUpload($excelFile);
            $em->persist($workbook);
            $em->flush();

            return $this->redirectToRoute('group_excel_import_preview', [
                'importId' => $workbook->getId(),
            ]);
        }

        return $this->render('excel-import/base-excel-import-start.twig', [
            'itemLabel' => 'Participant Groups',
            'importForm' => $form->createView(),
        ]);
    }

    /**
     * @Route("/excel-import/preview/{importId<\d+>}", name="group_excel_import_preview")
     */
    public function excelImportPreview(
        int $importId,
        ExcelImporter $excelImporter,
        ParticipantGroupAccessionIdGenerator $idGenerator
    ) {
        $em = $this->getDoctrine()->getManager();

        $importingWorkbook = $this->getDoctrine()
            ->getManager()
            ->find(ExcelImportWorkbook::class, $importId);

        $excelImporter->userMustHavePermissions($importingWorkbook);

        $importer = new ParticipantGroupImporter(
            $importingWorkbook->getFirstWorksheet(),
            $idGenerator
        );
        $importer->setEntityManager($em);

        $importer->process();

        return $this->render('participantGroup/excel-import-preview.html.twig', [
            'importId' => $importId,
            'importer' => $importer,
            'importPreviewTemplate' => 'participantGroup/excel-import-table.html.twig',
            'importCommitRoute' => 'group_excel_import_commit',
            'importCommitText' => 'Save Participant Groups',
        ]);
    }

    /**
     * @Route("/excel-import/commit/{importId<\d+>}", methods={"POST"}, name="group_excel_import_commit")
     */
    public function excelImportCommit(
        int $importId,
        ExcelImporter $excelImporter,
        ParticipantGroupAccessionIdGenerator $idGenerator
    ) {
        $em = $this->getDoctrine()
            ->getManager();

        $importingWorkbook = $em
            ->find(ExcelImportWorkbook::class, $importId);

        $excelImporter->userMustHavePermissions($importingWorkbook);

        $importer = new ParticipantGroupImporter(
            $importingWorkbook->getFirstWorksheet(),
            $idGenerator
        );
        $importer->setEntityManager($em);
        $importer->process(true);

        // Clean up workbook from the database
        $em->remove($importingWorkbook);

        $em->flush();

        return $this->render('participantGroup/excel-import-result.html.twig', [
            'importer' => $importer,
        ]);
    }


    private function findGroup($id): ParticipantGroup
    {
        $s = $this->getDoctrine()
            ->getRepository(ParticipantGroup::class)
            ->findOneByAnyId($id);

        if (!$s) {
            throw new \InvalidArgumentException('Cannot find Participant Group');
        }

        return $s;
    }
}

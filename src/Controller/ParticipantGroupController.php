<?php

namespace App\Controller;

use App\AccessionId\ParticipantGroupAccessionIdGenerator;
use App\Entity\DropOffSchedule;
use App\Entity\ExcelImportWorkbook;
use App\Entity\AuditLog;
use App\Entity\LabelPrinter;
use App\Entity\ParticipantGroup;
use App\ExcelImport\ExcelImporter;
use App\ExcelImport\ParticipantGroupImporter;
use App\Form\GenericExcelImportType;
use App\Form\ParticipantGroupForm;
use App\Label\ParticipantGroupBadgeLabelBuilder;
use App\Label\ZplPrinting;
use App\Scheduling\ParticipantGroupRoundRobinScheduler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
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
     * When POST for printing the request params should be
     *  - `groups` an array of group titles to be printed
     *
     * @Route(path="/", methods={"GET","POST"}, name="app_participant_group_list")
     */
    public function list(Request $request, ZplPrinting $zpl)
    {
        $this->denyAccessUnlessGranted('ROLE_PARTICIPANT_GROUP_VIEW');

        $groupRepo = $this->getDoctrine()->getRepository(ParticipantGroup::class);

        $form = $this->createFormBuilder()
            ->add('printer', EntityType::class, [
                'class' => LabelPrinter::class,
                'choice_name' => 'title',
                'required' => true,
                'empty_data' => "",
                'placeholder' => '- None -'
            ])
            ->add('print', SubmitType::class, [
                'label' => 'Print Selected Group Labels',
                'attr' => ['class' => 'btn-primary'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyAccessUnlessGranted('ROLE_PRINT_GROUP_LABELS');
            $data = $form->getData();
            $groupTitles = $request->request->get('groups', []);

            $printGroups = $groupRepo->findBy(['title' => $groupTitles]);

            $printer = $this->getDoctrine()->getRepository(LabelPrinter::class)->find($data['printer']);

            $builder = new ParticipantGroupBadgeLabelBuilder();
            $builder->setPrinter($printer);

            foreach ($printGroups as $group) {
                $builder->setGroup($group);
                $zpl->printBuilder($builder, $group->getParticipantCount());
            }
        }

        return $this->render('participantGroup/participant-group-list.html.twig', [
            'groups' => $groupRepo->findActiveAlphabetical(),
            'form' => $form->createView(),
        ]);
    }

    /**
     * Create a single new Group
     *
     * @Route(path="/new", methods={"GET", "POST"}, name="app_participant_group_new")
     */
    public function new(Request $request) : Response
    {
        // Requires admin privileges because this can impact assigned drop-off windows
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(ParticipantGroupForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $group = $form->getData();

            $em = $this->getDoctrine()->getManager();
            $em->persist($group);
            $em->flush();

            $scheduler = new ParticipantGroupRoundRobinScheduler();
            $scheduler->assignByDays(
                [$group],
                $em->getRepository(DropOffSchedule::class)->findDefaultSchedule()
            );
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
     * @Route("/{title}/edit", methods={"GET", "POST"}, name="app_participant_group_edit")
     */
    public function edit(string $title, Request $request) : Response
    {
        // Requires admin privileges because this can impact assigned drop-off windows
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $group = $this->findGroupByTitle($title);

        $form = $this->createForm(ParticipantGroupForm::class, $group);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->flush();

            return $this->redirectToRoute('app_participant_group_view', [
                'title' => $group->getTitle(),
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
     * @Route("/{title}", methods={"GET", "POST"}, name="app_participant_group_view")
     */
    public function view(string $title)
    {
        $this->denyAccessUnlessGranted('ROLE_PARTICIPANT_GROUP_VIEW');

        $group = $this->findGroupByTitle($title);

        $auditLogs = $this->getDoctrine()
            ->getRepository(AuditLog::class)
            ->getLogEntries($group);

        return $this->render('participantGroup/participant-group-view.html.twig', [
            'group' => $group,
            'auditLogs' => $auditLogs,
        ]);
    }

    /**
     * @Route("/{title}/deactivate", methods={"POST"}, name="app_participant_group_deactivate")
     */
    public function deactivate(string $title, EntityManagerInterface $em)
    {
        // Requires admin privileges because this can impact assigned drop-off windows
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $group = $this->findGroupByTitle($title);

        $group->setIsActive(false);
        // Clean up any drop off windows this group was using
        $group->clearDropOffWindows();

        $em->flush();

        return $this->redirectToRoute('app_participant_group_edit', [ 'title' => $group->getTitle() ]);
    }

    /**
     * @Route("/{title}/activate", methods={"POST"}, name="app_participant_group_activate")
     */
    public function activate(string $title, EntityManagerInterface $em)
    {
        // Requires admin privileges because this can impact assigned drop-off windows
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $group = $this->findGroupByTitle($title);

        $group->setIsActive(true);

        $em->flush();

        // Assign to the next available dropoff window
        $scheduler = new ParticipantGroupRoundRobinScheduler();
        $scheduler->assignByDays(
            [$group],
            $em->getRepository(DropOffSchedule::class)->findDefaultSchedule()
        );
        $em->flush();

        return $this->redirectToRoute('app_participant_group_edit', [ 'title' => $group->getTitle() ]);
    }

    /**
     * Print group labels
     *
     * @Route("/{title}/print", methods={"GET", "POST"}, name="app_participant_group_print")
     */
    public function print(string $title, Request $request, EntityManagerInterface $em, ZplPrinting $zpl)
    {
        $this->denyAccessUnlessGranted('ROLE_PRINT_GROUP_LABELS');

        $group = $this->findGroupByTitle($title);

        $form = $this->createFormBuilder()
            ->add('printer', EntityType::class, [
                'class' => LabelPrinter::class,
                'choice_name' => 'title',
                'required' => true,
                'empty_data' => "",
                'placeholder' => '- Select -'
            ])
            ->add('numToPrint', IntegerType::class, [
                'label' => 'Number of Labels',
                'data' => $group->getParticipantCount(),
                'attr' => [
                    'min' => 1,
                    'max' => 2000, // todo: max # per roll? reasonable batch size?
                ],
            ])
            ->add('send', SubmitType::class, [
                'label' => 'Print',
                'attr' => ['class' => 'btn-primary'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $printer = $em->getRepository(LabelPrinter::class)->find($data['printer']);
            $copies = $data['numToPrint'];

            $builder = new ParticipantGroupBadgeLabelBuilder();
            $builder->setPrinter($printer);
            $builder->setGroup($group);

            $zpl->printBuilder($builder, $copies);

            return $this->redirectToRoute('app_participant_group_list');
        }

        return $this->render('participantGroup/print-participant-group-labels.html.twig', [
            'group' => $group,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/excel-import/start", name="group_excel_import")
     */
    public function excelImport(Request $request, ExcelImporter $excelImporter)
    {
        $this->denyAccessUnlessGranted('ROLE_PARTICIPANT_GROUP_EDIT');

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

        return $this->render('excel-import/base-excel-import-start.html.twig', [
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
        $this->denyAccessUnlessGranted('ROLE_PARTICIPANT_GROUP_EDIT');

        $em = $this->getDoctrine()->getManager();

        $importingWorkbook = $this->mustFindImport($importId);
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
        $this->denyAccessUnlessGranted('ROLE_PARTICIPANT_GROUP_EDIT');

        $em = $this->getDoctrine()
            ->getManager();

        $importingWorkbook = $this->mustFindImport($importId);
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

        // Update group schedules
        $this->recalculateGroupSchedules();

        return $this->render('participantGroup/excel-import-result.html.twig', [
            'importer' => $importer,
        ]);
    }

    private function recalculateGroupSchedules()
    {
        $em = $this->getDoctrine()->getManager();
        $groupRepo = $em->getRepository(ParticipantGroup::class);

        // First, remove any groups that are no longer active
        $inactive = $groupRepo->findInactive();
        foreach ($inactive as $group) {
            $group->clearDropOffWindows();
        }

        // Must flush at this point so scheduler sees accurate view of the database
        $em->flush();

        // Assign new groups
        // NOTE: order by ID asc here so that assignment order matches the order they
        // appeared in the Excel file
        $active = $groupRepo->findBy(['isActive' => true], ['id' => 'ASC']);
        $toAssign = [];
        foreach ($active as $group) {
            if (count($group->getDropOffWindows()) > 0) continue;
            $toAssign[] = $group;
        }

        $scheduler = new ParticipantGroupRoundRobinScheduler();
        $scheduler->assignByDays(
            $toAssign,
            $em->getRepository(DropOffSchedule::class)->findDefaultSchedule()
        );

        // Commit changes from the scheduler
        $em->flush();
    }


    private function findGroupByTitle($title): ParticipantGroup
    {
        $s = $this->getDoctrine()
            ->getRepository(ParticipantGroup::class)
            ->findOneBy(['title' => $title]);

        if (!$s) {
            throw new \InvalidArgumentException('Cannot find Participant Group');
        }

        return $s;
    }

    private function mustFindImport(int $importId): ExcelImportWorkbook
    {
        $workbook = $this->getDoctrine()
            ->getManager()
            ->find(ExcelImportWorkbook::class, $importId);
        if (!$workbook) {
            throw new \InvalidArgumentException('Cannot find Import by ID');
        }

        return $workbook;
    }
}

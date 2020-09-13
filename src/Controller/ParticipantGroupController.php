<?php

namespace App\Controller;

use App\AccessionId\ParticipantGroupAccessionIdGenerator;
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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormInterface;
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
     * @Route(path="/", methods={"GET"}, name="app_participant_group_list")
     */
    public function list(Request $request, ZplPrinting $zpl)
    {
        $this->denyAccessUnlessGranted('ROLE_PARTICIPANT_GROUP_VIEW');

        $groupRepo = $this->getDoctrine()->getRepository(ParticipantGroup::class);

        $form = $this->getPrintForm();

        return $this->render('participantGroup/participant-group-list.html.twig', [
            'groups' => $groupRepo->findForList(),
            'form' => $form->createView(),
        ]);
    }

    /**
     * Create a single new Group
     *
     * @Route(path="/new", methods={"GET", "POST"}, name="app_participant_group_new")
     */
    public function new(Request $request, EntityManagerInterface $em) : Response
    {
        $this->denyAccessUnlessGranted('ROLE_PARTICIPANT_GROUP_EDIT');

        $form = $this->createForm(ParticipantGroupForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $group = $form->getData();

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
     * @Route("/{title}/edit", methods={"GET", "POST"}, name="app_participant_group_edit")
     */
    public function edit(string $title, Request $request, EntityManagerInterface $em) : Response
    {
        $this->denyAccessUnlessGranted('ROLE_PARTICIPANT_GROUP_EDIT');

        $group = $this->findGroupByTitle($title);

        $form = $this->createForm(ParticipantGroupForm::class, $group);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
     * Show a list of group names to let the user print new labels
     *
     * @Route(path="/print-group-label", methods={"GET"}, name="app_participant_group_print_list")
     */
    public function listPrint(Request $request, ZplPrinting $zpl)
    {
        $this->denyAccessUnlessGranted('ROLE_PRINT_GROUP_LABELS');

        $groupRepo = $this->getDoctrine()->getRepository(ParticipantGroup::class);

        $form = $this->getPrintForm();

        return $this->render('participantGroup/print-participant-group-labels.html.twig', [
            'groups' => $groupRepo->findActive(),
            'form' => $form->createView(),
        ]);
    }

    /**
     * Print group labels.
     *
     * Required POST params:
     *
     * - groups (string[]) ParticipantGroup.title to print
     *
     * @Route("/print", methods={"POST"}, name="app_participant_group_print")
     */
    public function print(Request $request, EntityManagerInterface $em, ZplPrinting $zpl)
    {
        $this->denyAccessUnlessGranted('ROLE_PRINT_GROUP_LABELS');

        $form = $this->getPrintForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $groupTitles = $request->request->get('groups', []);

            $printGroups = $em->getRepository(ParticipantGroup::class)->findBy(['title' => $groupTitles]);

            $builder = new ParticipantGroupBadgeLabelBuilder();
            $builder->setPrinter($data['printer']);

            foreach ($printGroups as $group) {
                $copies = empty($data['numToPrint']) ? $group->getParticipantCount() : $data['numToPrint'];
                $builder->setGroup($group);
                $zpl->printBuilder($builder, $copies);
            }
        }

        return $this->redirect($request->headers->get('referer'));
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
     * Deactivate a single Participant Group. Participants will no longer be
     * able to drop-off Specimens in this Group. Results will no longer be
     * sent to web hooks.
     *
     * @Route("/{title}/deactivate", methods={"POST"}, name="app_participant_group_deactivate")
     */
    public function deactivate(string $title, EntityManagerInterface $em)
    {
        $this->denyAccessUnlessGranted('ROLE_PARTICIPANT_GROUP_EDIT');

        $group = $this->findGroupByTitle($title);

        $group->setIsActive(false);

        $em->flush();

        return $this->redirectToRoute('app_participant_group_edit', [ 'title' => $group->getTitle() ]);
    }

    /**
     * Activate a single Participant Group.
     *
     * @Route("/{title}/activate", methods={"POST"}, name="app_participant_group_activate")
     */
    public function activate(string $title, EntityManagerInterface $em)
    {
        $this->denyAccessUnlessGranted('ROLE_PARTICIPANT_GROUP_EDIT');

        $group = $this->findGroupByTitle($title);

        $group->setIsActive(true);

        $em->flush();

        return $this->redirectToRoute('app_participant_group_edit', [ 'title' => $group->getTitle() ]);
    }

    /**
     * Display file upload form to begin import.
     * Saves uploaded file when form submitted.
     *
     * @Route("/excel-import/start", name="group_excel_import")
     */
    public function excelImport(Request $request, ExcelImporter $excelImporter, EntityManagerInterface $em)
    {
        $this->denyAccessUnlessGranted('ROLE_PARTICIPANT_GROUP_EDIT');

        // Import can take a long time with 1000+ rows
        $this->increaseExecutionTime();

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
     * Displays preview of data read from uploaded file.
     *
     * @Route("/excel-import/preview/{importId<\d+>}", name="group_excel_import_preview")
     */
    public function excelImportPreview(
        int $importId,
        ExcelImporter $excelImporter,
        ParticipantGroupAccessionIdGenerator $idGenerator,
        EntityManagerInterface $em
    ) {
        $this->denyAccessUnlessGranted('ROLE_PARTICIPANT_GROUP_EDIT');

        // Import can take a long time with 1000+ rows
        $this->increaseExecutionTime();

        $importingWorkbook = $this->mustFindImport($importId);
        $excelImporter->userMustHavePermissions($importingWorkbook);

        $importer = new ParticipantGroupImporter(
            $em,
            $importingWorkbook->getFirstWorksheet(),
            $idGenerator
        );

        $processedGroups = $importer->process();

        return $this->render('participantGroup/excel-import-preview.html.twig', [
            'importId' => $importId,
            'importer' => $importer,
            'processedGroups' => $processedGroups,
            'displayMultiWorksheetWarning' => count($importingWorkbook->getWorksheets()) > 1,
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
        ParticipantGroupAccessionIdGenerator $idGenerator,
        EntityManagerInterface $em
    ) {
        $this->denyAccessUnlessGranted('ROLE_PARTICIPANT_GROUP_EDIT');

        // Import can take a long time with 1000+ rows
        $this->increaseExecutionTime();

        $importingWorkbook = $this->mustFindImport($importId);
        $excelImporter->userMustHavePermissions($importingWorkbook);

        $importer = new ParticipantGroupImporter(
            $em,
            $importingWorkbook->getFirstWorksheet(),
            $idGenerator
        );
        $importer->process(true);

        // Clean up workbook from the database
        $em->remove($importingWorkbook);

        $em->flush();

        return $this->render('participantGroup/excel-import-result.html.twig', [
            'importer' => $importer,
        ]);
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

    private function getPrintForm(): FormInterface
    {
        return $this->createFormBuilder()
            ->add('printer', EntityType::class, [
                'class' => LabelPrinter::class,
                'choice_name' => 'title',
                'required' => true,
                'empty_data' => "",
                'placeholder' => '- Select -'
            ])
            ->add('numToPrint', IntegerType::class, [
                'label' => 'Number of Labels',
                'data' => 1,
                'attr' => [
                    'min' => 1,
                    'max' => 2000, // todo: max # per roll? reasonable batch size?
                ],
            ])
            ->add('print', SubmitType::class, [
                'label' => 'Print',
                'attr' => ['class' => 'btn-success'],
            ])
            ->setAction($this->generateUrl('app_participant_group_print'))
            ->getForm();
    }

    /**
     * Increases the allowed execution time for the current PHP script. Call
     * this method if you know that your controller action will be doing
     * operations that may be long-lasting
     *
     * @param int|number $addSeconds number of seconds to add, defaults to 300 (5 minutes)
     */
    private function increaseExecutionTime($addSeconds = 300)
    {
        $currentMaxSeconds = ini_get("max_execution_time");
        if (0 == $currentMaxSeconds) {
            // Already at max
            return;
        }

        $currMax = max(30, $currentMaxSeconds);
        set_time_limit($currMax + $addSeconds);
    }
}

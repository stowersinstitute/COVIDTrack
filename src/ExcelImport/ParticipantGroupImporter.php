<?php

namespace App\ExcelImport;

use App\AccessionId\ParticipantGroupAccessionIdGenerator;
use App\Entity\ExcelImportWorksheet;
use App\Entity\ParticipantGroup;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Import Participant Groups using Excel.
 */
class ParticipantGroupImporter extends BaseExcelImporter
{
    /** @var ParticipantGroupAccessionIdGenerator  */
    private $idGenerator;

    /**
     * Groups processed by this Importer
     *
     * @var ParticipantGroup[]
     */
    private $processedGroups = [];

    /**
     * Accumulates Title strings of Groups that have been imported in each upload.
     *
     * @var array[] Keys are the ParticipantGroup.title seen, value === true
     */
    private $seenTitles = [];

    public function __construct(EntityManagerInterface $em, ExcelImportWorksheet $worksheet, ParticipantGroupAccessionIdGenerator $idGenerator)
    {
        parent::__construct($worksheet);

        $this->setEntityManager($em);
        $this->idGenerator = $idGenerator;

        $this->columnMap = [
            'title' => 'A',
            'externalId' => 'B',
            'participantCount' => 'D',
            'isActive' => 'E',
            'acceptSaliva' => 'F',
            'acceptBlood' => 'G',
            'viralWebHookEnabled' => 'H',
            'antibodyWebHookEnabled' => 'I',
        ];
    }

    /**
     * OVERRIDDEN to match format in process()
     */
    public function getNumImportedItems(): int
    {
        if ($this->output === null) throw new \LogicException('Called getNumImportedItems before process()');

        $changedItems = 0;
        foreach ($this->output as $action => $groups) {
            $changedItems += count($groups);
        }

        return $changedItems;
    }

    /**
     * Returns true if there is at least one group associated with $action
     *
     * $action can be:
     *  - active
     *  - inactive
     *
     * See process()
     */
    public function hasGroupsForAction($action) : bool
    {
        return count($this->output[$action]) > 0;
    }

    /**
     * Processes the import
     *
     * Results will be stored in the $output property
     *
     * Messages (including errors) will be stored in the $messages property
     *
     * @return ParticipantGroup[]
     */
    public function process($commit = false)
    {
        if ($this->output !== null) {
            return $this->processedGroups;
        }

        $groupRepo = $this->em->getRepository(ParticipantGroup::class);

        $result = [
            'active' => [],
            'inactive' => [],
        ];

        for ($rowNumber = $this->startingRow; $rowNumber <= $this->worksheet->getNumRows(); $rowNumber++) {
            // If all values are blank assume it's just empty excel data
            if ($this->rowDataBlank($rowNumber)) continue;

            $rawExternalId = $this->worksheet->getCellValue($rowNumber, $this->columnMap['externalId']);
            $rawTitle = $this->worksheet->getCellValue($rowNumber, $this->columnMap['title']);
            $rawParticipantCount = $this->worksheet->getCellValue($rowNumber, $this->columnMap['participantCount']);
            $rawIsActive = $this->worksheet->getCellValue($rowNumber, $this->columnMap['isActive']);
            $rawAcceptSaliva = $this->worksheet->getCellValue($rowNumber, $this->columnMap['acceptSaliva']);
            $rawAcceptBlood = $this->worksheet->getCellValue($rowNumber, $this->columnMap['acceptBlood']);
            $rawViralWebHookEnabled = $this->worksheet->getCellValue($rowNumber, $this->columnMap['viralWebHookEnabled']);
            $rawAntibodyWebHookEnabled = $this->worksheet->getCellValue($rowNumber, $this->columnMap['antibodyWebHookEnabled']);

            // Validation methods return false if a field is invalid (and append to $this->messages)
            $rowOk = true;
            $rowOk = $rowOk && $this->validateExternalId($rawExternalId, $rowNumber);
            $rowOk = $rowOk && $this->validateTitle($rawTitle, $rowNumber);
            $rowOk = $rowOk && $this->validateParticipantCount($rawParticipantCount, $rowNumber);
            $rowOk = $rowOk && $this->validateIsActive($rawIsActive, $rowNumber);
            $rowOk = $rowOk && $this->validateAcceptSaliva($rawAcceptSaliva, $rowNumber);
            $rowOk = $rowOk && $this->validateAcceptBlood($rawAcceptBlood, $rowNumber);
            $rowOk = $rowOk && $this->validateViralWebHookEnabled($rawViralWebHookEnabled, $rowNumber);
            $rowOk = $rowOk && $this->validateAntibodyWebHookEnabled($rawAntibodyWebHookEnabled, $rowNumber);

            // If any field failed validation do not import the row
            if (!$rowOk) continue;

            $group = $groupRepo->findOneBy(['externalId' => $rawExternalId]);
            // New group
            if (!$group) {
                // Make it clear when previewing that this field is automatic
                $accessionId = '(automatic)';
                if ($commit) {
                    $accessionId = $this->idGenerator->generate();
                }

                $group = new ParticipantGroup(
                    $accessionId,
                    $rawParticipantCount ?? ParticipantGroup::MIN_PARTICIPANT_COUNT
                );

                if ($commit) {
                    $this->em->persist($group);
                }
            }
            // Existing group
            else {
                // Ensure entities won't be flush()ed if we're not committing
                if (!$commit) $this->em->detach($group);
            }

            $group->setExternalId($rawExternalId);
            $group->setTitle($rawTitle);
            $group->setParticipantCount($rawParticipantCount);
            $group->setIsActive($rawIsActive);
            $group->setAcceptsSalivaSpecimens($rawAcceptSaliva);
            $group->setAcceptsBloodSpecimens($rawAcceptBlood);
            $group->setViralResultsWebHooksEnabled($rawViralWebHookEnabled);
            $group->setAntibodyResultsWebHooksEnabled($rawAntibodyWebHookEnabled);

            $this->processedGroups[] = $group;
            $this->seenTitles[$rawTitle] = true;

            // Note: this does not guarantee any fields are changing, just that it was in the excel file
            if ($group->isActive()) {
                $result['active'][] = $group;
            } else {
                $result['inactive'][] = $group;
            }
        }

        $this->output = $result;

        return $this->processedGroups;
    }

    /**
     * Returns true if $raw is valid
     *
     * Otherwise, adds an error message to $this->messages and returns false
     */
    protected function validateExternalId($raw, $rowNumber): bool
    {
        if (!$raw) {
            $this->messages[] = ImportMessage::newError(
                'External ID cannot be blank',
                $rowNumber,
                $this->columnMap['externalId']
            );
            return false;
        }

        return true;
    }

    /**
     * Returns true if $raw is a participant count
     *
     * Otherwise, adds an error message to $this->messages and returns false
     */
    protected function validateParticipantCount($raw, $rowNumber): bool
    {
        if ($raw === null || $raw === '') {
            $this->messages[] = ImportMessage::newError(
                'Participant Count cannot be blank',
                $rowNumber,
                $this->columnMap['participantCount']
            );
            return false;
        }

        if ($raw < ParticipantGroup::MIN_PARTICIPANT_COUNT || $raw > ParticipantGroup::MAX_PARTICIPANT_COUNT) {
            $this->messages[] = ImportMessage::newError(
                sprintf('Participant Count must be between %d and %d', ParticipantGroup::MIN_PARTICIPANT_COUNT, ParticipantGroup::MAX_PARTICIPANT_COUNT),
                $rowNumber,
                $this->columnMap['participantCount']
            );
            return false;
        }

        return true;
    }

    /**
     * Returns true if $raw is a valid title
     *
     * Otherwise, adds an error message to $this->messages and returns false
     */
    protected function validateTitle($raw, $rowNumber) : bool
    {
        if (!$raw) {
            $this->messages[] = ImportMessage::newError(
                'Title cannot be blank',
                $rowNumber,
                $this->columnMap['title']
            );
            return false;
        }

        // Prevent duplicate rows for same Group
        if (isset($this->seenTitles[$raw])) {
            $this->messages[] = ImportMessage::newError(
                sprintf('Title "%s" appears multiple times in import. Can only appear once.', htmlentities($raw)),
                $rowNumber,
                $this->columnMap['externalId']
            );
            return false;
        }

        // Title must contain only things that the barcode scanner can read
        $allowedSpecial = [' ', '-', '_'];
        for ($i=0; $i < strlen($raw); $i++) {
            $char = $raw[$i];

            if ($char >= 'a' && $char <= 'z') continue;
            if ($char >= 'A' && $char <= 'Z') continue;
            if ($char >= '0' && $char <= '9') continue;
            if (in_array($char, $allowedSpecial)) continue;

            // Not in the list of allowed characters
            $this->messages[] = ImportMessage::newError(
                sprintf('Invalid character: %s', $char),
                $rowNumber,
                $this->columnMap['title']
            );

            return false;
        }

        return true;
    }

    /**
     * Returns true if $raw is a valid value for enabling/disabling this group.
     *
     * Otherwise, adds an error message to $this->messages and returns false
     */
    protected function validateIsActive($raw, $rowNumber): bool
    {
        $acceptedValues = [true, false];
        if (!in_array($raw, $acceptedValues, true)) {
            $this->messages[] = ImportMessage::newError(
                'Is Active? flag must be TRUE or FALSE',
                $rowNumber,
                $this->columnMap['isActive']
            );
            return false;
        }

        return true;
    }

    /**
     * Returns true if $raw is a valid value for Accept Saliva flag this group.
     *
     * Otherwise, adds an error message to $this->messages and returns false
     */
    protected function validateAcceptSaliva($raw, $rowNumber): bool
    {
        $acceptedValues = [true, false];
        if (!in_array($raw, $acceptedValues, true)) {
            $this->messages[] = ImportMessage::newError(
                'Accept Saliva? flag must be TRUE or FALSE',
                $rowNumber,
                $this->columnMap['acceptSaliva']
            );
            return false;
        }

        return true;
    }

    /**
     * Returns true if $raw is a valid value for Accept Blood flag this group.
     *
     * Otherwise, adds an error message to $this->messages and returns false
     */
    protected function validateAcceptBlood($raw, $rowNumber): bool
    {
        $acceptedValues = [true, false];
        if (!in_array($raw, $acceptedValues, true)) {
            $this->messages[] = ImportMessage::newError(
                'Accept Blood? flag must be TRUE or FALSE',
                $rowNumber,
                $this->columnMap['acceptBlood']
            );
            return false;
        }

        return true;
    }

    /**
     * Returns true if $raw is a valid value for Viral Web Hook Enabled flag this group.
     *
     * Otherwise, adds an error message to $this->messages and returns false
     */
    protected function validateViralWebHookEnabled($raw, $rowNumber): bool
    {
        $acceptedValues = [true, false];
        if (!in_array($raw, $acceptedValues, true)) {
            $this->messages[] = ImportMessage::newError(
                'Viral Web Hook Enabled? flag must be TRUE or FALSE',
                $rowNumber,
                $this->columnMap['viralWebHookEnabled']
            );
            return false;
        }

        return true;
    }

    /**
     * Returns true if $raw is a valid value for Antibody Web Hook Enabled flag this group.
     *
     * Otherwise, adds an error message to $this->messages and returns false
     */
    protected function validateAntibodyWebHookEnabled($raw, $rowNumber): bool
    {
        $acceptedValues = [true, false];
        if (!in_array($raw, $acceptedValues, true)) {
            $this->messages[] = ImportMessage::newError(
                'Antibody Web Hook Enabled? flag must be TRUE or FALSE',
                $rowNumber,
                $this->columnMap['antibodyWebHookEnabled']
            );
            return false;
        }

        return true;
    }
}

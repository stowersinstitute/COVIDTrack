<?php

namespace App\ExcelImport;

use App\AccessionId\ParticipantGroupAccessionIdGenerator;
use App\Entity\ExcelImportWorksheet;
use App\Entity\ParticipantGroup;

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

    public function __construct(ExcelImportWorksheet $worksheet, ParticipantGroupAccessionIdGenerator $idGenerator)
    {
        parent::__construct($worksheet);

        $this->idGenerator = $idGenerator;

        $this->columnMap = [
            'externalId' => 'C',
            'participantCount' => 'H',
            'title' => 'J',
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
     *  - created
     *  - updated
     *  - deactivated
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
            'created' => [],
            'updated' => [],
            'deactivated' => []
        ];

        // Created and updated can be figured out from the Excel file
        for ($rowNumber = $this->startingRow; $rowNumber <= $this->worksheet->getNumRows(); $rowNumber++) {
            // If all values are blank assume it's just empty excel data
            if ($this->rowDataBlank($rowNumber)) continue;

            $rawExternalId = $this->worksheet->getCellValue($rowNumber, $this->columnMap['externalId']);
            $rawTitle = $this->worksheet->getCellValue($rowNumber, $this->columnMap['title']);
            $rawParticipantCount = $this->worksheet->getCellValue($rowNumber, $this->columnMap['participantCount']);

            // Validation methods return false if a field is invalid (and append to $this->messages)
            $rowOk = true;
            $rowOk = $this->validateExternalId($rawExternalId, $rowNumber) && $rowOk;
            $rowOk = $this->validateTitle($rawTitle, $rowNumber) && $rowOk;
            $rowOk = $this->validateParticipantCount($rawParticipantCount, $rowNumber) && $rowOk;

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

                $result['created'][] = $group;
                if ($commit) {
                    $this->em->persist($group);
                }
            }
            // Existing group
            else {
                // Note: this does not guarantee any fields are changing, just that it was in the excel file
                $result['updated'][] = $group;

                // Ensure entities won't be flush()ed if we're not committing
                if (!$commit) $this->em->detach($group);
            }

            $group->setExternalId($rawExternalId);
            $group->setTitle($rawTitle);
            $group->setParticipantCount($rawParticipantCount);
            $group->setIsActive(true);

            $this->processedGroups[] = $group;
        }

        // Deactivated is everything not in the excel file
        $toDeactivate = $groupRepo->findActiveNotIn($result['updated']);
        foreach ($toDeactivate as $group) {
            // Ensure entities won't be flush()ed if we're not committing
            if (!$commit) $this->em->detach($group);
            $result['deactivated'][] = $group;

            $group->setIsActive(false);

            $this->processedGroups[] = $group;
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
                'Sys ID cannot be blank',
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
        if ($raw < ParticipantGroup::MIN_PARTICIPANT_COUNT) {
            $this->messages[] = ImportMessage::newError(
                'Participant count cannot be less than ' . ParticipantGroup::MIN_PARTICIPANT_COUNT,
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
}
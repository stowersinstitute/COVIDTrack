<?php

namespace App\Tests\ExcelImport;

use App\AccessionId\ParticipantGroupAccessionIdGenerator;
use App\Entity\ExcelImportWorkbook;
use App\Entity\ParticipantGroup;
use App\ExcelImport\ParticipantGroupImporter;
use App\Tests\BaseDatabaseTestCase;
use App\Tests\ExcelImport\DataFixtures\ParticipantGroupImportUpdatingFixtures;

/**
 * Tests importing Participant Groups using Excel.
 */
class ParticipantGroupImporterTest extends BaseDatabaseTestCase
{
    public function testProcessNewGroups()
    {
        $workbook = ExcelImportWorkbook::createFromFilePath(__DIR__ . '/workbooks/participant-group-importer-new.xlsx');
        $idGenerator = $this->buildMockParticipantGroupIdGen();
        $importer = new ParticipantGroupImporter($this->em, $workbook->getFirstWorksheet(), $idGenerator);

        // This list should match what's in participant-group-importer.xlsx
        // Order not important
        $expectedExternalGroupIds = [
            'SN1',
            'SN2',
            'SN3',
            'SN4',
        ];

        $groups = $importer->process(true);

        $this->assertCount(count($expectedExternalGroupIds), $groups);
        $this->assertSame([], $importer->getErrors(), 'Import has errors when not expected to have any');
        $this->assertSame(count($expectedExternalGroupIds), $importer->getNumImportedItems());

        // Verify processed list has expected External IDs
        $this->mustContainAllExternalGroupIds($expectedExternalGroupIds, $groups);
    }

    public function testProcessUpdatingGroups()
    {
        $this->loadFixtures([
            ParticipantGroupImportUpdatingFixtures::class,
        ]);

        $workbook = ExcelImportWorkbook::createFromFilePath(__DIR__ . '/workbooks/participant-group-importer-updating.xlsx');
        $idGenerator = $this->buildMockParticipantGroupIdGen();
        $importer = new ParticipantGroupImporter($this->em, $workbook->getFirstWorksheet(), $idGenerator);

        // This list should match what's in participant-group-importer.xlsx
        // Order not important
        $expectedExternalGroupIds = [
            'SNUP1',
            'SNUP2',
            'SNUP3',
            'SNUP4',
        ];

        $groups = $importer->process(true);

        $this->assertCount(count($expectedExternalGroupIds), $groups);
        $this->assertSame([], $importer->getErrors(), 'Import has errors when not expected to have any');
        $this->assertSame(count($expectedExternalGroupIds), $importer->getNumImportedItems());

        // Verify processed list has expected External IDs
        $this->mustContainAllExternalGroupIds($expectedExternalGroupIds, $groups);

        $expectedParticipantCounts = [
            'SNUP1' => 10,
            'SNUP2' => 11,
            'SNUP3' => 12,
            'SNUP4' => 13,
        ];
        foreach ($groups as $group) {
            if (!isset($expectedParticipantCounts[$group->getExternalId()])) {
                $this->fail('Cannot find expected participantCount for Group ' . $group->getExternalId());
            }

            $count = $expectedParticipantCounts[$group->getExternalId()];
            $this->assertSame($count, $group->getParticipantCount());
        }
    }

    /**
     * @param string[] $expectedExternalIds Array of expected ParticipantGroup.externalId[]
     * @param ParticipantGroup[]   $groups
     */
    private function mustContainAllExternalGroupIds(array $expectedExternalIds, array $groups)
    {
        $processedExternalId = array_map(function(ParticipantGroup $G) {
            return $G->getExternalId();
        }, $groups);
        $missingIds = array_filter($expectedExternalIds, function($mustHaveId) use ($processedExternalId) {
            return !in_array($mustHaveId, $processedExternalId);
        });

        $this->assertSame([], $missingIds, 'Processed Participant Groups missing these External IDs');
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|ParticipantGroupAccessionIdGenerator
     */
    private function buildMockParticipantGroupIdGen()
    {
        $builder = $this->getMockBuilder(ParticipantGroupAccessionIdGenerator::class)
            ->disableOriginalConstructor();

        $mock = $builder->getMock();
        $mock
            ->expects($this->any())
            ->method('generate')
            ->willReturnCallback(function() {
                if (empty($counter)) {
                    static $counter = 0;
                }

                $counter++;

                return sprintf("MOCKGRP-%d", $counter);
            });

        return $mock;
    }
}

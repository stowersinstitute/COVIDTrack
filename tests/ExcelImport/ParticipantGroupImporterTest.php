<?php

namespace App\Tests\ExcelImport;

use App\AccessionId\ParticipantGroupAccessionIdGenerator;
use App\Entity\ExcelImportWorkbook;
use App\Entity\ParticipantGroup;
use App\ExcelImport\ParticipantGroupImporter;

/**
 * Tests importing Participant Groups using Excel.
 */
class ParticipantGroupImporterTest extends BaseExcelImporterTestCase
{
    public function testProcessNewGroups()
    {
        $workbook = ExcelImportWorkbook::createFromFilePath(__DIR__ . '/workbooks/participant-group-importer.xlsx');
        $idGenerator = $this->buildMockParticipantGroupIdGen();
        $importer = new ParticipantGroupImporter($workbook->getFirstWorksheet(), $idGenerator);
        $importer->setEntityManager($this->em);

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
            ->setConstructorArgs([$this->buildMockEntityManager()]);

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

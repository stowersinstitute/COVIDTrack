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

        // This list should match what's in XLSX file above.
        // Order not important
        $expectedExternalGroupIds = [
            'SN1',
            'SN2',
            'SN3',
            'SN4',
            '09876543210987654321abcdefABCDEF',
        ];

        $groups = $importer->process(true);

        // Verify rows with missing required data report as user-readable errors.
        // XLSX file above has red cells where errors expected.
        $errors = $importer->getErrors();
        $expectedErrors = [
            ['rowNumber' => 8],
            ['rowNumber' => 9],
            ['rowNumber' => 10],
            ['rowNumber' => 11],
            ['rowNumber' => 12],
        ];
        $this->assertCount(count($expectedErrors), $errors);
        foreach ($expectedErrors as $expectedError) {
            $found = false;
            foreach ($errors as $error) {
                if ($error->getRowNumber() === $expectedError['rowNumber']) {
                    $found = true;
                }
            }
            if (!$found) {
                $this->fail(sprintf('Expected to find error for data in Row %d but no error found', $expectedError['rowNumber']));
            }
        }

        $this->assertCount(count($expectedExternalGroupIds), $groups);
        $this->assertSame(count($expectedExternalGroupIds), $importer->getNumImportedItems());

        // Verify processed list has expected External IDs
        $this->mustContainAllExternalGroupIds($expectedExternalGroupIds, $groups);

        // Verify processed list has expected Web Hook Results status based on Title
        $titleToExpectedEnabled = [
            'First' => false,
            'Second' => false,
            'Third' => false,
            'Fourth' => false,
            '09876543210987654321abcdefABCDEF' => true,
        ];
        foreach ($groups as $group) {
            $title = $group->getTitle();
            if (!isset($titleToExpectedEnabled[$title])) {
                $this->fail(sprintf('Assertion missing case for Group Title "%s"', $title));
            }

            $this->assertSame($titleToExpectedEnabled[$title], $group->isEnabledForResultsWebHooks(), sprintf('Group %s has wrong Web Hook Enabled status', $title));
        }
    }

    public function testProcessUpdatingGroups()
    {
        $this->loadFixtures([
            ParticipantGroupImportUpdatingFixtures::class,
        ]);

        $workbook = ExcelImportWorkbook::createFromFilePath(__DIR__ . '/workbooks/participant-group-importer-updating.xlsx');
        $idGenerator = $this->buildMockParticipantGroupIdGen();
        $importer = new ParticipantGroupImporter($this->em, $workbook->getFirstWorksheet(), $idGenerator);

        // This list should match what's in XLSX file above.
        // Order not important
        $expectedExternalGroupIds = [
            'SNUP1',
            'SNUP2',
            'SNUP3',
            'SNUP4',
            '09876543210987654321abcdefABCDEF',
        ];

        $groups = $importer->process(true);

        $this->assertSame([], $importer->getErrors(), 'Import has errors when not expected to have any');
        $this->assertCount(count($expectedExternalGroupIds), $groups);
        $this->assertSame(count($expectedExternalGroupIds), $importer->getNumImportedItems());

        // Verify processed list has expected External IDs
        $this->mustContainAllExternalGroupIds($expectedExternalGroupIds, $groups);

        // Verify Participant Count updated to what's in Excel file
        $expectedParticipantCounts = [
            'SNUP1' => 10,
            'SNUP2' => 11,
            'SNUP3' => 12,
            'SNUP4' => 13,
            '09876543210987654321abcdefABCDEF' => 1,
        ];
        foreach ($groups as $group) {
            if (!isset($expectedParticipantCounts[$group->getExternalId()])) {
                $this->fail('Cannot find expected participantCount for Group ' . $group->getExternalId());
            }

            $count = $expectedParticipantCounts[$group->getExternalId()];
            $this->assertSame($count, $group->getParticipantCount());
        }

        // Verify fixture Groups not in update file remain with correct active status
        $inactiveGroup = $this->findGroupByExternalId('AlwaysInactiveGroup');
        $this->assertInstanceOf(ParticipantGroup::class, $inactiveGroup);
        $this->assertFalse($inactiveGroup->isActive());
        $activeGroup = $this->findGroupByExternalId('AlwaysActiveGroup');
        $this->assertInstanceOf(ParticipantGroup::class, $activeGroup);
        $this->assertTrue($activeGroup->isActive());

        // Verify processed list has expected Web Hook Results status based on Title
        $titleToExpectedEnabled = [
            'Ten' => true,
            'Eleven' => false,
            'Twelve' => true,
            'Thirteen' => false,
            '09876543210987654321abcdefABCDEF' => false,
        ];
        foreach ($groups as $group) {
            $title = $group->getTitle();
            if (!isset($titleToExpectedEnabled[$title])) {
                $this->fail(sprintf('Assertion missing case for Group Title "%s"', $title));
            }

            $this->assertSame($titleToExpectedEnabled[$title], $group->isEnabledForResultsWebHooks(), sprintf('Group %s has wrong Web Hook Enabled status', $title));
        }
    }

    public function testImportAcceptedWithoutWebHookColumn()
    {
        $workbook = ExcelImportWorkbook::createFromFilePath(__DIR__ . '/workbooks/participant-group-importer-without-webhook-column.xlsx');
        $idGenerator = $this->buildMockParticipantGroupIdGen();
        $importer = new ParticipantGroupImporter($this->em, $workbook->getFirstWorksheet(), $idGenerator);

        // This list should match what's in XLSX file above.
        // Order not important
        $expectedExternalGroupIds = [
            'SN100',
            'SN200',
            'SN300',
            'SN400',
        ];

        $groups = $importer->process(true);

        // Verify rows with missing required data report as user-readable errors.
        // XLSX file above has red cells where errors expected.
        $errors = $importer->getErrors();
        $this->assertCount(0, $errors);

        $this->assertCount(count($expectedExternalGroupIds), $groups);
        $this->assertSame(count($expectedExternalGroupIds), $importer->getNumImportedItems());

        // Verify processed list has expected External IDs
        $this->mustContainAllExternalGroupIds($expectedExternalGroupIds, $groups);

        // Verify processed list has expected Web Hook Results status based on Title
        $titleToExpectedEnabled = [
            'OneJ' => false,
            'TwoJ' => false,
            'ThreeJ' => false,
            'FourJ' => false,
        ];
        foreach ($groups as $group) {
            $title = $group->getTitle();
            if (!isset($titleToExpectedEnabled[$title])) {
                $this->fail(sprintf('Assertion missing case for Group Title "%s"', $title));
            }

            $this->assertSame($titleToExpectedEnabled[$title], $group->isEnabledForResultsWebHooks(), sprintf('Group %s has wrong Web Hook Enabled status', $title));
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

    private function findGroupByExternalId(string $externalId): ?ParticipantGroup
    {
        return $this->em
            ->getRepository(ParticipantGroup::class)
            ->findOneByExternalId($externalId);
    }
}

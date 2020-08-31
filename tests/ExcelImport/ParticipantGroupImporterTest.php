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

        $groups = $importer->process(true);

        // Verify rows with missing required data report as user-readable errors.
        // XLSX file above has red cells where errors expected.
        $errors = $importer->getErrors();
        $expectedErrors = [
            ['rowNumber' => 8], // External ID missing
            ['rowNumber' => 9], // Participant Count missing
            ['rowNumber' => 10], // Title missing
            ['rowNumber' => 14], // Is Active? missing
            ['rowNumber' => 15], // Is Active? invalid
            ['rowNumber' => 17], // Accept Saliva missing
            ['rowNumber' => 18], // Accept Saliva invalid
            ['rowNumber' => 19], // Accept Blood flag
            ['rowNumber' => 20], // Accept Blood invalid
            ['rowNumber' => 21], // Saliva Web Hook missing
            ['rowNumber' => 22], // Saliva Web Hook invalid
            ['rowNumber' => 23], // Blood Web Hook missing
            ['rowNumber' => 24], // Blood Web Hook invalid
        ];
        $this->assertCount(count($expectedErrors), $errors, 'Found wrong number of expected errors');
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

        // This list should match what's in XLSX file above.
        // Order not important
        $expectedExternalGroupIds = [
            'SN1',
            'SN2',
            'SN3',
            'SN4',
            '09876543210987654321abcdefABCDEF',
            'SN5',
        ];
        $this->assertCount(count($expectedExternalGroupIds), $groups);
        $this->assertSame(count($expectedExternalGroupIds), $importer->getNumImportedItems());
        $this->mustContainAllExternalGroupIds($expectedExternalGroupIds, $groups);

        // Verify processed list has expected "Is Active?" flag, lookup by Title
        $titleToIsActive = [
            'First' => true,
            'Second' => true,
            'Third' => true,
            'Fourth' => true,
            '09876543210987654321abcdefABCDEF' => true,
            'Fifth' => false,
        ];
        foreach ($groups as $group) {
            $title = $group->getTitle();
            if (!isset($titleToIsActive[$title])) {
                $this->fail(sprintf('Assertion missing case for Group Title "%s"', $title));
            }

            $this->assertSame($titleToIsActive[$title], $group->isActive(), sprintf('Group "%s" has wrong Is Active status', $title));
        }

        // Verify processed list has expected "Accept Saliva?" flag, lookup by Title
        $titleToAcceptSaliva = [
            'First' => true,
            'Second' => true,
            'Third' => true,
            'Fourth' => true,
            '09876543210987654321abcdefABCDEF' => false,
            'Fifth' => false,
        ];
        foreach ($groups as $group) {
            $title = $group->getTitle();
            if (!isset($titleToAcceptSaliva[$title])) {
                $this->fail(sprintf('Assertion missing case for Group Title "%s"', $title));
            }

            $this->assertSame($titleToAcceptSaliva[$title], $group->acceptsSalivaSpecimens(), sprintf('Group "%s" has wrong Accept Saliva? status', $title));
        }

        // Verify processed list has expected "Accept Blood?" flag, lookup by Title
        $titleToAcceptBlood = [
            'First' => true,
            'Second' => true,
            'Third' => true,
            'Fourth' => false,
            '09876543210987654321abcdefABCDEF' => false,
            'Fifth' => true,
        ];
        foreach ($groups as $group) {
            $title = $group->getTitle();
            if (!isset($titleToAcceptBlood[$title])) {
                $this->fail(sprintf('Assertion missing case for Group Title "%s"', $title));
            }

            $this->assertSame($titleToAcceptBlood[$title], $group->acceptsBloodSpecimens(), sprintf('Group "%s" has wrong Accept Blood? status', $title));
        }

        // Verify processed list has expected "Viral Web Hooks Enabled?" flag, lookup by Title
        $titleToViralWebHooksEnabled = [
            'First' => true,
            'Second' => true,
            'Third' => false,
            'Fourth' => false,
            '09876543210987654321abcdefABCDEF' => false,
            'Fifth' => true,
        ];
        foreach ($groups as $group) {
            $title = $group->getTitle();
            if (!isset($titleToViralWebHooksEnabled[$title])) {
                $this->fail(sprintf('Assertion missing case for Group Title "%s"', $title));
            }

            $this->assertSame($titleToViralWebHooksEnabled[$title], $group->viralResultsWebHooksEnabled(), sprintf('Group "%s" has wrong Viral Web Hooks Enabled? status', $title));
        }

        // Verify processed list has expected "Antibody Web Hooks Enabled?" flag, lookup by Title
        $titleToAntibodyWebHooksEnabled = [
            'First' => true,
            'Second' => false,
            'Third' => false,
            'Fourth' => false,
            '09876543210987654321abcdefABCDEF' => false,
            'Fifth' => true,
        ];
        foreach ($groups as $group) {
            $title = $group->getTitle();
            if (!isset($titleToAntibodyWebHooksEnabled[$title])) {
                $this->fail(sprintf('Assertion missing case for Group Title "%s"', $title));
            }

            $this->assertSame($titleToAntibodyWebHooksEnabled[$title], $group->antibodyResultsWebHooksEnabled(), sprintf('Group "%s" has wrong Antibody Web Hooks Enabled? status', $title));
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
            'ToggleToActiveGroup',
            'ToggleToInactiveGroup',
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
            'ToggleToActiveGroup' => 7,
            'ToggleToInactiveGroup' => 8,
        ];
        foreach ($groups as $group) {
            if (!isset($expectedParticipantCounts[$group->getExternalId()])) {
                $this->fail('Cannot find expected participantCount for Group ' . $group->getExternalId());
            }

            $count = $expectedParticipantCounts[$group->getExternalId()];
            $this->assertSame($count, $group->getParticipantCount());
        }

        // Verify processed list has expected "Is Active?" flag, lookup by Title
        $titleToIsActive = [
            'Ten' => true,
            'Eleven' => true,
            'Twelve' => true,
            'Thirteen' => true,
            '09876543210987654321abcdefABCDEF' => true,
            'ToggleToActiveGroup' => true,
            'ToggleToInactiveGroup' => false,
        ];
        foreach ($groups as $group) {
            $title = $group->getTitle();
            if (!isset($titleToIsActive[$title])) {
                $this->fail(sprintf('Assertion missing case for Group Title "%s"', $title));
            }

            $this->assertSame($titleToIsActive[$title], $group->isActive(), sprintf('Group "%s" has wrong Is Active status', $title));
        }

        // Verify fixture Groups not in update file remain with correct active status
        $inactiveGroup = $this->findGroupByExternalId('AlwaysInactiveGroup');
        $this->assertInstanceOf(ParticipantGroup::class, $inactiveGroup);
        $this->assertFalse($inactiveGroup->isActive());
        $activeGroup = $this->findGroupByExternalId('AlwaysActiveGroup');
        $this->assertInstanceOf(ParticipantGroup::class, $activeGroup);
        $this->assertTrue($activeGroup->isActive());
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

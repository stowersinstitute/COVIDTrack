<?php

namespace App\Tests\Command\WebHook;

use App\Api\WebHook\Client\ResultHttpClient;
use App\Api\WebHook\Response\WebHookResponse;
use App\Command\WebHook\ResultCommand;
use App\Entity\SpecimenResultQPCR;
use App\Tests\BaseDatabaseTestCase;
use App\Tests\Command\WebHook\DataFixtures\ResultCommandViralFixtures;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class ResultCommandViralTest extends BaseDatabaseTestCase
{
    public function testSendsViralResultsForConfiguredGroups()
    {
        $extractor = $this->loadFixtures([
            ResultCommandViralFixtures::class,
        ]);
        $referenceRepository = $extractor->getReferenceRepository();

        $mockHttpClient = $this->buildMockHttpClient();
        $cmd = new ResultCommand($this->em, $mockHttpClient);

        $cmdTester = new CommandTester($cmd);
        $cmdTester->execute([], [
            'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
        ]);

        $txtOutput = $cmdTester->getDisplay();

        // Groups from ResultCommandViralFixtures
        $externalIdsExpected = [
            ResultCommandViralFixtures::EXTID_GROUP_3,
            ResultCommandViralFixtures::EXTID_GROUP_5,
        ];
        foreach ($externalIdsExpected as $externalId) {
            $this->assertStringContainsString($externalId, $txtOutput);
        }
        $externalIdsNotExpected = [
            ResultCommandViralFixtures::EXTID_GROUP_1,
            ResultCommandViralFixtures::EXTID_GROUP_2,
            ResultCommandViralFixtures::EXTID_GROUP_4,
        ];
        foreach ($externalIdsNotExpected as $externalId) {
            $this->assertStringNotContainsString($externalId, $txtOutput);
        }

        // Running again should not display Groups in the output
        // because the results were already sent
        $cmdTester->execute([], [
            'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
        ]);
        $txtOutput = $cmdTester->getDisplay();

        // Groups from ResultCommandViralFixtures
        $externalIdsNotExpected = [
            ResultCommandViralFixtures::EXTID_GROUP_1,
            ResultCommandViralFixtures::EXTID_GROUP_2,
            ResultCommandViralFixtures::EXTID_GROUP_3,
            ResultCommandViralFixtures::EXTID_GROUP_4,
            ResultCommandViralFixtures::EXTID_GROUP_5,
        ];
        foreach ($externalIdsNotExpected as $externalId) {
            $this->assertStringNotContainsString($externalId, $txtOutput);
        }

        // Wait, so below Result update can occur after lastWebHookSuccessAt
        sleep(1);

        // Now update some existing results from another Group
        // and assert users notified
        /** @var SpecimenResultQPCR $resultToUpdate */
        $resultToUpdate = $referenceRepository->getReference('ViralResultToUpdate');
        $resultToUpdate->setConclusion(SpecimenResultQPCR::CONCLUSION_POSITIVE);
        $this->em->flush();

        // Re-execute command, we expect it to notify about this updated result
        $cmdTester->execute([], [
            'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
        ]);
        $txtOutput = $cmdTester->getDisplay();

        // Groups from ResultCommandViralFixtures
        $externalIdsExpected = [
            $resultToUpdate->getSpecimen()->getParticipantGroup()->getExternalId(),
        ];
        foreach ($externalIdsExpected as $externalId) {
            $this->assertStringContainsString($externalId, $txtOutput);
        }
        $externalIdsNotExpected = [
            ResultCommandViralFixtures::EXTID_GROUP_1,
            ResultCommandViralFixtures::EXTID_GROUP_2,
            ResultCommandViralFixtures::EXTID_GROUP_3,
            ResultCommandViralFixtures::EXTID_GROUP_4,
        ];
        foreach ($externalIdsNotExpected as $externalId) {
            $this->assertStringNotContainsString($externalId, $txtOutput);
        }
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|ResultHttpClient
     */
    private function buildMockHttpClient()
    {
        $httpClient = $this->createMock(ResultHttpClient::class);

        // Wire to return mock successful response
        $response = $this->createMock(WebHookResponse::class);
        $response->method("getStatusCode")->willReturn(200);
        $response->method("getHeaders")->willReturn([]);
        $response->method("getBodyContents")->willReturn("");

        $httpClient->method("post")->willReturn($response);

        return $httpClient;
    }
}

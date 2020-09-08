<?php

namespace App\Tests\Command\WebHook;

use App\Api\WebHook\Client\ServiceNowHttpClient;
use App\Api\WebHook\Response\ServiceNowWebHookResponse;
use App\Command\WebHook\ResultCommand;
use App\Entity\SpecimenResultAntibody;
use App\Entity\SpecimenResultQPCR;
use App\Tests\BaseDatabaseTestCase;
use App\Tests\Command\WebHook\DataFixtures\ResultCommandAntibodyFixtures;
use App\Tests\Command\WebHook\DataFixtures\ResultCommandViralFixtures;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class ResultCommandTest extends BaseDatabaseTestCase
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

    public function testSendsAntibodyResultsForConfiguredGroups()
    {
        $extractor = $this->loadFixtures([
            ResultCommandAntibodyFixtures::class,
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
            ResultCommandAntibodyFixtures::EXTID_GROUP_C,
            ResultCommandAntibodyFixtures::EXTID_GROUP_A,
        ];
        foreach ($externalIdsExpected as $externalId) {
            $this->assertStringContainsString($externalId, $txtOutput);
        }
        $externalIdsNotExpected = [
            ResultCommandAntibodyFixtures::EXTID_GROUP_E,
            ResultCommandAntibodyFixtures::EXTID_GROUP_D,
            ResultCommandAntibodyFixtures::EXTID_GROUP_B,
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
            ResultCommandAntibodyFixtures::EXTID_GROUP_E,
            ResultCommandAntibodyFixtures::EXTID_GROUP_D,
            ResultCommandAntibodyFixtures::EXTID_GROUP_C,
            ResultCommandAntibodyFixtures::EXTID_GROUP_B,
            ResultCommandAntibodyFixtures::EXTID_GROUP_A,
        ];
        foreach ($externalIdsNotExpected as $externalId) {
            $this->assertStringNotContainsString($externalId, $txtOutput);
        }

        // Now update some existing results from another Group
        // and assert users notified
        /** @var SpecimenResultAntibody $resultToUpdate */
        $resultToUpdate = $referenceRepository->getReference('AntibodyResultToUpdate');
        $resultToUpdate->setConclusion(SpecimenResultAntibody::CONCLUSION_POSITIVE);
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
            ResultCommandAntibodyFixtures::EXTID_GROUP_E,
            ResultCommandAntibodyFixtures::EXTID_GROUP_D,
            ResultCommandAntibodyFixtures::EXTID_GROUP_C,
            ResultCommandAntibodyFixtures::EXTID_GROUP_B,
        ];
        foreach ($externalIdsNotExpected as $externalId) {
            $this->assertStringNotContainsString($externalId, $txtOutput);
        }
    }

    /**
     * @param string $pathToResponseBodyJson Local file path to .json file to be used as Response
     * @return \PHPUnit\Framework\MockObject\MockObject|ServiceNowHttpClient
     */
    private function buildMockHttpClient(/*string $pathToResponseBodyJson*/)
    {
//        $responseBodyJson = file_get_contents($pathToResponseBodyJson);
//        if (false === $responseBodyJson) {
//            throw new \RuntimeException('Cannot load mock JSON Response Body at path: ' . $pathToResponseBodyJson);
//        }

        $httpClient = $this->createMock(ServiceNowHttpClient::class);

        // Wire to return mock successful response
        $response = $this->getMockBuilder(ServiceNowWebHookResponse::class)
            ->disableOriginalConstructor()
            // Don't mock these methods
            ->setMethodsExcept([
                'updateResultWebHookStatus', // So update status logic runs
            ])
            ->getMock();
        $response->method("getStatusCode")->willReturn(200);
        $response->method("getHeaders")->willReturn([
            'Date' => [
                'Mon, 07 Sep 2020 16:07:50 GMT',
            ],
        ]);
        $response->method("getBodyContents")->willReturn("");

        $httpClient->method("post")->willReturn($response);

        return $httpClient;
    }
}

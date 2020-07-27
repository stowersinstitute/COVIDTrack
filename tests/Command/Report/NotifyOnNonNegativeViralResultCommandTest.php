<?php

namespace App\Tests\Command\Report;

use App\Command\Report\NotifyOnNonNegativeViralResultCommand;
use App\Email\EmailBuilder;
use App\Entity\SpecimenResultQPCR;
use App\Tests\BaseDatabaseTestCase;
use App\Tests\Command\DataFixtures\NotifyOnNonNegativeViralResultsFixtures;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\RouterInterface;

class NotifyOnNonNegativeViralResultCommandTest extends BaseDatabaseTestCase
{
    public function testSendsNotifications()
    {
        $extractor = $this->loadFixtures([
            NotifyOnNonNegativeViralResultsFixtures::class,
        ]);
        $referenceRepository = $extractor->getReferenceRepository();

        $emailBuilder = $this->buildEmailBuilder();
        $mockMailer = $this->buildMockMailer();
        $mockRouter = $this->buildMockRouter();

        $cmd = new NotifyOnNonNegativeViralResultCommand($this->em, $emailBuilder, $mockMailer, $mockRouter);

        $cmdTester = new CommandTester($cmd);
        $cmdTester->execute([], [
            'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
        ]);

        $txtOutput = $cmdTester->getDisplay();

        // Groups from NotifyOnRecommendedCliaViralResultsFixtures
        // that have Non-Negative results
        $groupsExpected = [
            'Yellow',
            'Purple',
        ];
        foreach ($groupsExpected as $groupTitle) {
            $this->assertStringContainsString($groupTitle, $txtOutput);
        }

        // Users with notification role from NotifyOnRecommendedCliaViralResultsFixtures
        $expectedUserRecipients = [
            'Mary Smith',
            'Admin User',
        ];
        foreach ($expectedUserRecipients as $userText) {
            $this->assertStringContainsString($userText, $txtOutput);
        }

        // Running again should not display users in the output because it's not sending email
        $cmdTester->execute([], [
            'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
        ]);
        $txtOutput = $cmdTester->getDisplay();

        $userRecipientsNotFound = [
            'Mary Smith',
            'Admin User',
        ];
        foreach ($userRecipientsNotFound as $userText) {
            // Users names should NOT be present!!!
            $this->assertStringNotContainsString($userText, $txtOutput);
        }

        // Now update some existing results from another Group
        // and assert users notified
        /** @var SpecimenResultQPCR $resultToUpdate */
        $resultToUpdate = $referenceRepository->getReference('ViralResult.Gray.NoResult');
        $resultToUpdate->setConclusion(SpecimenResultQPCR::CONCLUSION_NON_NEGATIVE);
        $this->em->flush();

        // Re-execute command, we expect it to notify about this updated result
        $cmdTester->execute([], [
            'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
        ]);
        $txtOutput = $cmdTester->getDisplay();

        // Verify updated result's Participant Group displays in email
        $groupsExpected = [
            $resultToUpdate->getSpecimen()->getParticipantGroup()->getTitle(),
        ];
        foreach ($groupsExpected as $groupTitle) {
            $this->assertStringContainsString($groupTitle, $txtOutput);
        }

        // Verify updated results sent to proper users
        $expectedUserRecipients = [
            'Mary Smith',
            'Admin User',
        ];
        foreach ($expectedUserRecipients as $userText) {
            $this->assertStringContainsString($userText, $txtOutput);
        }
    }

    private function buildEmailBuilder()
    {
        $fromAddress = 'test-from@covidtrack.org';
        $replyToAddress = 'test-from@covidtrack.org';
        $isTest = true;

        return new EmailBuilder($fromAddress, $replyToAddress, $isTest);
    }

    private function buildMockMailer()
    {
        $mock = $this->createMock(MailerInterface::class);

        return $mock;
    }

    private function buildMockRouter()
    {
        $mock = $this->createMock(RouterInterface::class);

        return $mock;
    }
}

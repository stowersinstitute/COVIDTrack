<?php

namespace App\Tests\Command\Report;

use App\Command\Report\NotifyOnPositiveResultCommand;
use App\Email\EmailBuilder;
use App\Entity\SpecimenResultQPCR;
use App\Tests\BaseDatabaseTestCase;
use App\Tests\Command\DataFixtures\NotifyOnNewlyCreatedPositiveResultsFixtures;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\RouterInterface;

class NotifyOnPositiveResultCommandTest extends BaseDatabaseTestCase
{
    public function testSendsNotifications()
    {
        $extractor = $this->loadFixtures([
            NotifyOnNewlyCreatedPositiveResultsFixtures::class,
        ]);
        $referenceRepository = $extractor->getReferenceRepository();

        $emailBuilder = $this->buildEmailBuilder();
        $mockMailer = $this->buildMockMailer();
        $mockRouter = $this->buildMockRouter();

        $cmd = new NotifyOnPositiveResultCommand($this->em, $emailBuilder, $mockMailer, $mockRouter);

        $cmdTester = new CommandTester($cmd);
        $cmdTester->execute([], [
            'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
        ]);

        $txtOutput = $cmdTester->getDisplay();

        // Groups from NotifyOnNewlyCreatedPositiveResultsFixtures
        $groupsExpected = [
            'Orange',
            'Red',
        ];
        foreach ($groupsExpected as $groupTitle) {
            $this->assertStringContainsString($groupTitle, $txtOutput);
        }

        // Users with notification role from NotifyOnNewlyCreatedPositiveResultsFixtures
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
            $this->assertStringNotContainsString($userText, $txtOutput);
        }

        // Now update some existing results from another Group
        // and assert users notified
        /** @var SpecimenResultQPCR $resultToUpdate */
        $resultToUpdate = $referenceRepository->getReference('ViralResult.Gray.NoResult');
        $resultToUpdate->setConclusion(SpecimenResultQPCR::CONCLUSION_POSITIVE);
        $this->em->flush();

        // Re-execute command, we expect it to notify about this updated result
        $cmdTester->execute([], [
            'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
        ]);
        $txtOutput = $cmdTester->getDisplay();

        $groupsExpected = [
            $resultToUpdate->getSpecimen()->getParticipantGroup()->getTitle(),
        ];
        foreach ($groupsExpected as $groupTitle) {
            $this->assertStringContainsString($groupTitle, $txtOutput);
        }
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

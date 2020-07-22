<?php

namespace App\Tests\Command\Report;

use App\Command\Report\NotifyOnPositiveResultCommand;
use App\Email\EmailBuilder;
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
        $this->loadFixtures([
            NotifyOnNewlyCreatedPositiveResultsFixtures::class,
        ]);

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

        $expectedUserRecipients = [
            'Mary Smith',
            'Admin User',
        ];
        foreach ($expectedUserRecipients as $userText) {
            $this->assertStringNotContainsString($userText, $txtOutput);
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

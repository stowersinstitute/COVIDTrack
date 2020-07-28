<?php

namespace App\Tests\Command\Report;

use App\Command\Report\NotifyOnAntibodyResultsCommand;
use App\Email\EmailBuilder;
use App\Entity\SpecimenResultAntibody;
use App\Tests\BaseDatabaseTestCase;
use App\Tests\Command\DataFixtures\NotifyOnAntibodyResultsFixtures;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\RouterInterface;

class NotifyOnAntibodyResultsCommandTest extends BaseDatabaseTestCase
{
    public function testSendsNotifications()
    {
        $extractor = $this->loadFixtures([
            NotifyOnAntibodyResultsFixtures::class,
        ]);
        $referenceRepository = $extractor->getReferenceRepository();

        $emailBuilder = $this->buildEmailBuilder();
        $mockMailer = $this->buildMockMailer();
        $mockRouter = $this->buildMockRouter();

        $cmd = new NotifyOnAntibodyResultsCommand($this->em, $emailBuilder, $mockMailer, $mockRouter);

        $cmdTester = new CommandTester($cmd);
        $cmdTester->execute([], [
            'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
        ]);

        $txtOutput = $cmdTester->getDisplay();

        // Groups from NotifyOnAntibodyResultsFixtures
        $groupsExpected = [
            'GroupOne',
            'GroupThree',
            'GroupFour',
        ];
        foreach ($groupsExpected as $groupTitle) {
            $this->assertStringContainsString($groupTitle, $txtOutput);
        }

        // Verify Control Participant Group with a Positive result is not notified
        $this->assertStringNotContainsString('ControlGroup', $txtOutput);

        // Users with notification role from NotifyOnAntibodyResultsFixtures
        $expectedUserRecipients = [
            'Jessie Smith',
            'Privileged User',
        ];
        foreach ($expectedUserRecipients as $userText) {
            $this->assertStringContainsString($userText, $txtOutput);
        }

        // 3. Running again should not display users in the output because it's not sending email
        $cmdTester->execute([], [
            'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
        ]);
        $txtOutput = $cmdTester->getDisplay();

        $userRecipientsNotFound = [
            'Jessie Smith',
            'Privileged User',
        ];
        foreach ($userRecipientsNotFound as $userText) {
            $this->assertStringNotContainsString($userText, $txtOutput, 'Found recipient when did not expect to');
        }

        // 4. Now update some existing results from another Group
        // and assert users notified
        /** @var SpecimenResultAntibody $resultFromNotContactedGroup */
        $resultFromNotContactedGroup = $referenceRepository->getReference('AntibodyResult.GroupFive.NegativeResult1');
        $resultFromNotContactedGroup->setConclusion(SpecimenResultAntibody::CONCLUSION_POSITIVE);

        $referenceRepository
            ->getReference('AntibodyResult.GroupFive.NegativeResult2')
            ->setConclusion(SpecimenResultAntibody::CONCLUSION_POSITIVE);

        /** @var SpecimenResultAntibody $resultFromContactedGroup */
        $resultFromContactedGroup = $referenceRepository->getReference('AntibodyResult.GroupOne.NegativeResult');
        $resultFromContactedGroup->setConclusion(SpecimenResultAntibody::CONCLUSION_POSITIVE);
        $this->em->flush();

        // Re-execute command, we expect it to notify about this updated result
        $cmdTester->execute([], [
            'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
        ]);
        $txtOutput = $cmdTester->getDisplay();

        // Above code updated 2 results for "GroupFive"
        // Verify the group only appears once in above results
        $updatedGroupTitle = $resultFromNotContactedGroup->getSpecimen()->getParticipantGroup()->getTitle();
        $groupFoundInOutputNumTimes = substr_count($txtOutput, $updatedGroupTitle);
        $this->assertSame(1, $groupFoundInOutputNumTimes);

        // Verify updated results sent to correct users
        $expectedUserRecipients = [
            'Jessie Smith',
            'Privileged User',
        ];
        foreach ($expectedUserRecipients as $userText) {
            $this->assertStringContainsString($userText, $txtOutput);
        }

        // Verify previous Participant Groups contacted before are not in the updated email
        // Groups from NotifyOnAntibodyResultsFixtures with results other than Negative
        $groupsNotExpected = [
            'GroupOne',
            'GroupThree',
            'GroupFour',
        ];
        foreach ($groupsNotExpected as $groupText) {
            $this->assertStringNotContainsString($groupText, $txtOutput, 'Found group when did not expect to');
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

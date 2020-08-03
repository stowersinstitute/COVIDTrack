<?php

namespace App\DataFixtures;

use App\Command\Report\BaseResultsNotificationCommand;
use App\Entity\AntibodyNotification;
use App\Entity\ParticipantGroup;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Fixtures for Antibody Notifications.
 */
class AppAntibodyNotificationFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies()
    {
        return [
            AppParticipantGroupsFixtures::class,
        ];
    }

    public function load(ObjectManager $em)
    {
        foreach ($this->getData() as $data) {
            $n = new AntibodyNotification();
            $n->setCreatedAt($data['sentAt']);

            $n->setToAddressesString('Study Coordinator <coordinator@no-reply>');
            $n->setSubject('Antibody Results Notification');

            foreach ($data['groups'] as $group) {
                $n->addRecommendedGroup($group);
            }

            $message = $this->buildFixtureMessage($data['groups'], $data['sentAt']);
            $n->setMessage($message);

            $em->persist($n);
        }

        $em->flush();
    }

    private function getData(): array
    {
        return [
            [
                'sentAt' => new \DateTimeImmutable('-14 days 10:00am'),
                'groups' => [
                    $this->getReference('group.Red'),
                    $this->getReference('group.Orange'),
                ],
            ],
            [
                'sentAt' => new \DateTimeImmutable('-13 days 11:00am'),
                'groups' => [
                    $this->getReference('group.Yellow'),
                ],
            ],
            [
                'sentAt' => new \DateTimeImmutable('-12 days 12:00pm'),
                'groups' => [
                    $this->getReference('group.Green'),
                ],
            ],
            [
                'sentAt' => new \DateTimeImmutable('-11 days 1:00pm'),
                'groups' => [
                    $this->getReference('group.Blue'),
                ],
            ],
        ];
    }

    /**
     * @param ParticipantGroup[] $groups
     */
    private function buildFixtureMessage(array $groups, \DateTimeInterface $sentAt): string
    {
        // Convert to [ [$group, $timestamp], [$group, $timestamp], ... ]
        $results = array_map(function(ParticipantGroup $group) use ($sentAt) {
            return [$group, $sentAt];
        }, $groups);

        $url = 'http://covidtracktesting.domain.com/';

        $groupResultsHtmlLines = array_map(function(array $tupleResult) {
            /** @var ParticipantGroup $group */
            /** @var \DateTimeInterface $updatedAt */
            list($group, $updatedAt) = $tupleResult;

            return sprintf('<tr><td>%s</td><td>%s</td></tr>', $group->getTitle(), $updatedAt->format(BaseResultsNotificationCommand::RESULTS_DATETIME_FORMAT));
        }, $results);

        return sprintf("
            <p>One or more members of these Participant Groups had a non-negative or stronger antibody response in recent results:</p>

            <table class='results-table'>
                <thead>
                    <tr>
                         <th>Group</th>
                         <th>Results Published</th>
                    </tr>
                </thead>
                <tbody>
%s
                </tbody>
            </table>

            <p>
                View more details in COVIDTrack:<br>%s
            </p>

<style>
.results-table {
    border:1px solid #333;
}
.results-table th {
    text-align:left;
}
.results-table td, .results-table th {
    padding:0.25em;
    border-bottom: 1px solid #ccc;
}
</style>
        ",
            implode("\n", $groupResultsHtmlLines),
            sprintf('<a href="%s">%s</a>', htmlentities($url), $url)
        );
    }
}

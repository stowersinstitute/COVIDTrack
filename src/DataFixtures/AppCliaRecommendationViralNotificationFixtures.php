<?php

namespace App\DataFixtures;

use App\Entity\ParticipantGroup;
use App\Entity\CliaRecommendationViralNotification;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Fixtures for CLIA Recommendation Notifications.
 */
class AppCliaRecommendationViralNotificationFixtures extends Fixture implements DependentFixtureInterface
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
            $n = new CliaRecommendationViralNotification();
            $n->setCreatedAt($data['sentAt']);

            $n->setToAddressesString('Study Coordinator <coordinator@no-reply>');
            $n->setSubject('CLIA Recommendation Notification');

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
        $groupsRecTestingOutput = array_map(function(ParticipantGroup $g) {
            return sprintf('<li>%s</li>', $g->getTitle());
        }, $groups);

        $timestampsOutput = array_map(function(\DateTimeImmutable $dt) {
            return sprintf("<li>%s</li>", $dt->format('Y-m-d g:ia'));
        }, [$sentAt]);

        $url = 'http://covidtracktesting.domain.com/';

        // Example message, it's OK if this drifts out of date with real implementation
        return sprintf("
            <p>Participant Groups have been recommended for diagnostic testing.</p>

            <p>Results published:</p>
            <ul>
%s
            </ul>

            <p>Participant Groups:</p>
            <ul>
%s
            </ul>

            <p>
                View more details in COVIDTrack:<br>%s
            </p>
        ",
            implode("\n", $timestampsOutput),
            implode("\n", $groupsRecTestingOutput),
            sprintf('<a href="%s">%s</a>', htmlentities($url), $url)
        );
    }
}

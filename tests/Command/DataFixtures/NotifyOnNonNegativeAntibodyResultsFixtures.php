<?php

namespace App\Tests\Command\DataFixtures;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Command\Report\BaseResultsNotificationCommand;
use App\Entity\AppUser;
use App\Entity\DropOff;
use App\Entity\ParticipantGroup;
use App\Entity\SpecimenResultAntibody;
use App\Entity\Tube;
use App\Entity\WellPlate;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Creates test data for testing sending email notifications about
 * Participant Groups with Antibody Results recommending testing.
 */
class NotifyOnNonNegativeAntibodyResultsFixtures extends Fixture
{
    /**
     * @var SpecimenAccessionIdGenerator
     */
    private $specimenIdGen;

    public function __construct()
    {
        // Create mock SpecimenAccessionIdGenerator that generates known values
        $this->specimenIdGen = new class extends SpecimenAccessionIdGenerator {
            // Overwrite constructor to not require any params
            public function __construct() {}

            // Overwrite generate() to only generate valid values
            public function generate() {
                if (empty($counter)) {
                    static $counter = 0;
                }

                $counter++;

                return sprintf("Notif%d", $counter);
            }
        };
    }

    private function getUserData()
    {
        return [
            [
                'username' => 'jessie',
                'displayName' => 'Jessie Smith',
                'email' => 'jessie@example.com',
                'notify' => true,
            ],
            [
                'username' => 'privileged',
                'displayName' => 'Privileged User',
                'email' => 'privileged@example.com',
                'notify' => true,
            ],
            [
                'username' => 'john',
                'displayName' => 'John Doe',
                'email' => 'john@example.com',
                'notify' => false,
            ],
        ];
    }

    public function load(ObjectManager $em)
    {
        // Create users, some with ability to receive the notification
        foreach ($this->getUserData() as $data) {
            $user = new AppUser($data['username']);
            $user->setDisplayName($data['displayName']);
            $user->setEmail($data['email']);

            if ($data['notify'] === true) {
                $user->addRole(BaseResultsNotificationCommand::NOTIFY_USERS_WITH_ROLE_OLD);
            }

            $em->persist($user);
        }

        // This Well Plate will hold the Specimens
        $wellPlate = new WellPlate('NotifBloodPlate');
        $em->persist($wellPlate);

        // Groups
        foreach ($this->getGroupData() as $data) {
            $group = new ParticipantGroup($data['title'], $data['participantCount']);
            $group->setTitle($data['title']);

            if (isset($data['isControl'])) {
                $group->setIsControl($data['isControl']);
            }

            $this->setReference('group.' . $data['title'], $group);

            $em->persist($group);
        }

        // Simulate printing labels for Tubes
        foreach ($this->getTubeData() as $data) {
            $tube = new Tube($data['accessionId']);
            $this->addReference($data['accessionId'], $tube);

            $em->persist($tube);
        }
        $em->flush();

        // Simulate Tube drop-off and check-in
        foreach ($this->getTubeData() as $data) {
            /** @var Tube $tube */
            $tube = $this->getReference($data['accessionId']);

            // Kiosk Dropoff
            /** @var ParticipantGroup $group */
            $group = $this->getReference('group.' . $data['participantGroup']);
            $tubeType = $data['tubeType'];
            $collectedAt = $data['collectedAt'];
            $dropOff = new DropOff();
            // NOTE: Specimen.accessionId generated with known value. See __construct() above
            $tube->kioskDropoffComplete($this->specimenIdGen, $dropOff, $group, $tubeType, $collectedAt);

            // Accepted Check-in
            $checkinUsername = 'test-checkin-user';
            $tube->markAccepted($checkinUsername);

            // Tubes/Specimens added to a Well Plate
            $tube->addToWellPlate($wellPlate, $data['wellPlatePosition']);

            // Result, if available
            if (isset($data['resultConclusion'])) {
                $well = $tube->getSpecimen()->getWellsOnPlate($wellPlate)[0];

                $result = new SpecimenResultAntibody($well, $data['resultConclusion']);
                $result->setCreatedAt(new \DateTimeImmutable('-4 days 10:00am'));
                $result->setUpdatedAt(new \DateTimeImmutable('-4 days 10:00am'));

                if (isset($data['resultReferenceId'])) {
                    $this->addReference($data['resultReferenceId'], $result);
                }

                $em->persist($result);
            }
        }

        $em->flush();
    }

    public function getGroupData(): array
    {
        return [
            [
                'title' => 'GroupOne',
                'participantCount' => 5,
            ],
            [
                'title' => 'GroupTwo',
                'participantCount' => 6,
            ],
            [
                'title' => 'GroupThree',
                'participantCount' => 7,
            ],
            [
                'title' => 'GroupFour',
                'participantCount' => 8,
            ],
            [
                'title' => 'GroupFive',
                'participantCount' => 9,
            ],
            [
                'title' => 'GroupControl',
                'participantCount' => 0,
                'isControl' => true,
            ],
        ];
    }

    public function getTubeData(): array
    {
        return [
            [
                'accessionId' => 'NotifBloodTube0001',
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => 'GroupOne',
                'wellPlatePosition' => 'A1',
                'resultConclusion' => SpecimenResultAntibody::CONCLUSION_POSITIVE,
            ],
            [
                'accessionId' => 'NotifBloodTube0002',
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => 'GroupTwo',
                'wellPlatePosition' => 'A2',
                'resultConclusion' => SpecimenResultAntibody::CONCLUSION_NEGATIVE,
            ],
            [
                'accessionId' => 'NotifBloodTube0003',
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => 'GroupThree',
                'wellPlatePosition' => 'A3',
                'resultConclusion' => SpecimenResultAntibody::CONCLUSION_NON_NEGATIVE,
            ],
            [
                'accessionId' => 'NotifBloodTube0004',
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => 'GroupFour',
                'wellPlatePosition' => 'A4',
                'resultConclusion' => SpecimenResultAntibody::CONCLUSION_NON_NEGATIVE,
            ],
            [
                'accessionId' => 'NotifBloodTube0005',
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => 'GroupFive',
                'wellPlatePosition' => 'A5',
//                'resultConclusion' => null, Does not yet have a result
            ],
            [
                'accessionId' => 'NotifBloodTube0006',
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => 'GroupControl',
                'wellPlatePosition' => 'A6',
                'resultConclusion' => SpecimenResultAntibody::CONCLUSION_POSITIVE,
            ],
            [
                'accessionId' => 'NotifBloodTube0007',
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => 'GroupFive',
                'wellPlatePosition' => 'A7',
                'resultReferenceId' => 'AntibodyResult.GroupFive.NoResult',
                'resultConclusion' => SpecimenResultAntibody::CONCLUSION_NEGATIVE,
            ],
        ];
    }
}

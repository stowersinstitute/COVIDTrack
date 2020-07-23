<?php

namespace App\Tests\Command\DataFixtures;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Command\Report\BaseResultsNotificationCommand;
use App\Entity\AppUser;
use App\Entity\DropOff;
use App\Entity\ParticipantGroup;
use App\Entity\SpecimenResultQPCR;
use App\Entity\Tube;
use App\Entity\WellPlate;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Creates test data for sending email notifications about
 * Participant Groups with Non-Negative Viral results.
 */
class NotifyOnNonNegativeResultsFixtures extends Fixture
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
                'username' => 'mary',
                'displayName' => 'Mary Smith',
                'email' => 'mary@example.com',
                'notify' => true,
            ],
            [
                'username' => 'admin',
                'displayName' => 'Admin User',
                'email' => 'admin@example.com',
                'notify' => true,
            ],
            [
                'username' => 'james',
                'displayName' => 'James Doe',
                'email' => 'james@example.com',
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
        $wellPlate = new WellPlate('NotifPlate');
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

                $result = new SpecimenResultQPCR($well, $data['resultConclusion']);
                $result->setCreatedAt(new \DateTimeImmutable('-4 days'));
                $result->setUpdatedAt(new \DateTimeImmutable('-4 days'));

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
                'title' => 'Orange',
                'participantCount' => 5,
            ],
            [
                'title' => 'Red',
                'participantCount' => 6,
            ],
            [
                'title' => 'Yellow',
                'participantCount' => 7,
            ],
            [
                'title' => 'Purple',
                'participantCount' => 8,
            ],
            [
                'title' => 'Gray',
                'participantCount' => 9,
            ],
            [
                'title' => 'Control',
                'participantCount' => 0,
                'isControl' => true,
            ],
        ];
    }

    public function getTubeData(): array
    {
        return [
            [
                'accessionId' => 'NotifTube0001',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => 'Orange',
                'wellPlatePosition' => 'A1',
                'resultConclusion' => SpecimenResultQPCR::CONCLUSION_POSITIVE,
            ],
            [
                'accessionId' => 'NotifTube0002',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => 'Red',
                'wellPlatePosition' => 'A2',
                'resultConclusion' => SpecimenResultQPCR::CONCLUSION_RECOMMENDED,
            ],
            [
                'accessionId' => 'NotifTube0003',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => 'Yellow',
                'wellPlatePosition' => 'A3',
                'resultConclusion' => SpecimenResultQPCR::CONCLUSION_NON_NEGATIVE,
            ],
            [
                'accessionId' => 'NotifTube0004',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => 'Purple',
                'wellPlatePosition' => 'A4',
                'resultConclusion' => SpecimenResultQPCR::CONCLUSION_NON_NEGATIVE,
            ],
            [
                'accessionId' => 'NotifTube0005',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => 'Gray',
                'wellPlatePosition' => 'A5',
//                'resultConclusion' => null, Does not yet have a result
            ],
            [
                'accessionId' => 'NotifTube0006',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => 'Control',
                'wellPlatePosition' => 'A6',
                'resultConclusion' => SpecimenResultQPCR::CONCLUSION_POSITIVE,
            ],
            [
                'accessionId' => 'NotifTube0007',
                'tubeType' => Tube::TYPE_SALIVA,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'participantGroup' => 'Gray',
                'wellPlatePosition' => 'A7',
                'resultReferenceId' => 'ViralResult.Gray.NoResult',
                'resultConclusion' => SpecimenResultQPCR::CONCLUSION_NEGATIVE,
            ],
        ];
    }
}

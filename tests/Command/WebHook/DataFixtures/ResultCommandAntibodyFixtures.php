<?php

namespace App\Tests\Command\WebHook\DataFixtures;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Entity\DropOff;
use App\Entity\ParticipantGroup;
use App\Entity\SpecimenResultAntibody;
use App\Entity\Tube;
use App\Entity\WellPlate;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Creates test data for testing sending results to a Web Hook.
 */
class ResultCommandAntibodyFixtures extends Fixture
{
    const EXTID_GROUP_A = 'GROUP-A';
    const EXTID_GROUP_B = 'GROUP-B';
    const EXTID_GROUP_C = 'GROUP-C';
    const EXTID_GROUP_D = 'GROUP-D';
    const EXTID_GROUP_E = 'GROUP-E';

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

                return sprintf("Results%d", $counter);
            }
        };
    }

    public function load(ObjectManager $em)
    {
        // This Well Plate will hold the Specimens
        $wellPlate = new WellPlate('ResultsPlate');
        $em->persist($wellPlate);

        // Groups
        foreach ($this->getGroupData() as $data) {
            $participantCount = 5;
            $group = new ParticipantGroup($data['title'], $participantCount);
            $group->setTitle($data['title']);
            $group->setExternalId($data['externalId']);

            $group->setIsActive($data['isActive']);
            $group->setAcceptsSalivaSpecimens(true);
            $group->setAcceptsBloodSpecimens(true);
            $group->setViralResultsWebHooksEnabled($data['viralResultsWebHooksEnabled']);
            $group->setAntibodyResultsWebHooksEnabled($data['antibodyResultsWebHooksEnabled']);

            if (isset($data['isControl'])) {
                $group->setIsControl($data['isControl']);
            }

            $this->addReference('group.' . $data['externalId'], $group);

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
            $group = $this->getReference('group.' . $data['participantGroupExternalId']);
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

            // Viral Result, if available
            if (isset($data['resultConclusion'])) {
                $well = $tube->getSpecimen()->getWellsOnPlate($wellPlate)[0];

                $result = new SpecimenResultAntibody($well, $data['resultConclusion']);
                $result->setCreatedAt(new \DateTimeImmutable('-4 days 9:00am'));
                $result->setUpdatedAt(new \DateTimeImmutable('-4 days 9:00am'));

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
                'title' => 'Blood Active With Hooks Disabled',
                'externalId' => self::EXTID_GROUP_E,
                'participantCount' => 5,
                'isActive' => true,
                'viralResultsWebHooksEnabled' => false,
                'antibodyResultsWebHooksEnabled' => false, // Don't send
            ],
            [
                'title' => 'Blood Inactive With Hooks Enabled',
                'externalId' => self::EXTID_GROUP_D,
                'participantCount' => 5,
                'isActive' => false, // Being inactive prevents sending results
                'viralResultsWebHooksEnabled' => true,
                'antibodyResultsWebHooksEnabled' => true,
            ],
            [
                'title' => 'Blood Hooks Enabled',
                'externalId' => self::EXTID_GROUP_C,
                'participantCount' => 5,
                'isActive' => true,
                'viralResultsWebHooksEnabled' => true,
                'antibodyResultsWebHooksEnabled' => true,
            ],
            [
                'title' => 'Blood Hooks Disabled',
                'externalId' => self::EXTID_GROUP_B,
                'participantCount' => 5,
                'isActive' => true,
                'viralResultsWebHooksEnabled' => false,  // Don't send
                'antibodyResultsWebHooksEnabled' => false,  // Don't send
            ],
            [
                'title' => 'Blood Begin With No Completed Results',
                'externalId' => self::EXTID_GROUP_A,
                'participantCount' => 5,
                'isActive' => true,
                'viralResultsWebHooksEnabled' => true,
                'antibodyResultsWebHooksEnabled' => true,
            ],
        ];
    }

    public function getTubeData(): array
    {
        return [
            // Blood Tubes, Antibody Results
            [
                'participantGroupExternalId' => self::EXTID_GROUP_E,
                'accessionId' => 'ResultBloodTube0001',
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'wellPlatePosition' => 'B1',
                'resultConclusion' => SpecimenResultAntibody::CONCLUSION_POSITIVE,
            ],
            [
                'participantGroupExternalId' => self::EXTID_GROUP_D,
                'accessionId' => 'ResultBloodTube0002',
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'wellPlatePosition' => 'B2',
                'resultConclusion' => SpecimenResultAntibody::CONCLUSION_NON_NEGATIVE,
            ],
            [
                'participantGroupExternalId' => self::EXTID_GROUP_C,
                'accessionId' => 'ResultBloodTube0003',
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'wellPlatePosition' => 'B3',
                'resultConclusion' => SpecimenResultAntibody::CONCLUSION_POSITIVE,
            ],
            [
                'participantGroupExternalId' => self::EXTID_GROUP_B,
                'accessionId' => 'ResultBloodTube0004',
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'wellPlatePosition' => 'B4',
                'resultConclusion' => SpecimenResultAntibody::CONCLUSION_POSITIVE,
            ],
            [
                'resultReferenceId' => 'AntibodyResultToUpdate',
                'participantGroupExternalId' => self::EXTID_GROUP_A,
                'accessionId' => 'ResultBloodTube0005',
                'tubeType' => Tube::TYPE_BLOOD,
                'collectedAt' => new \DateTimeImmutable('-1 day 9:45am'),
                'wellPlatePosition' => 'B5',
                'resultConclusion' => SpecimenResultAntibody::CONCLUSION_NEGATIVE,
            ],
        ];
    }
}

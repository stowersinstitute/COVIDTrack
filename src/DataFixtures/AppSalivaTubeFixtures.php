<?php

namespace App\DataFixtures;

use App\AccessionId\SpecimenAccessionIdGenerator;
use App\Entity\KioskSession;
use App\Entity\KioskSessionTube;
use App\Entity\ParticipantGroup;
use App\Entity\Tube;
use App\Entity\WellPlate;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Import Tubes used for Blood collection
 */
class AppSalivaTubeFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies()
    {
        return [
            AppSystemConfigurationEntryFixtures::class,
            AppParticipantGroupsFixtures::class,
            AppWellPlateFixtures::class,
            AppKioskFixtures::class,
        ];
    }

    /**
     * @var ParticipantGroup[]
     */
    private $allGroups;

    /**
     * Generates Species Accession IDs
     *
     * @var SpecimenAccessionIdGenerator
     */
    private $specimenAccessionIdGen;

    public function __construct(SpecimenAccessionIdGenerator $specIdGen)
    {
        $this->specimenAccessionIdGen = $specIdGen;
    }

    public function load(ObjectManager $em)
    {
        $this->distributedTubes($em);
        $this->returnedTubes($em);
        $this->acceptedTubes($em);
        $this->rejectedTubes($em);

        // Must flush because Tecan import example has hardcoded IDs.
        // Below code can't detect existing tubes unless flushed first.
        $em->flush();

        $this->tubesForTecanExample($em);
    }

    /**
     * Tubes that have their label printed and distributed.
     */
    private function distributedTubes(ObjectManager $em)
    {
        // Fixtures require at least 250 distributed tubes for Tecan import example to work.
        // See $this->tubesForTecanExample()
        $numToCreate = 250;
        for ($i=1; $i<= $numToCreate; $i++) {
            $em->persist(new Tube());
        }
    }

    /**
     * Tubes that Participants have returned at a Kiosk and are ready for
     * Technician check-in.
     */
    private function returnedTubes(ObjectManager $em)
    {
        $numToCreate = 50;
        for ($i=1; $i<= $numToCreate; $i++) {
            $T = new Tube();

            // Tube Specimens will have been collected (extracted) from the
            // Participant within the last few days
            $collectedAt = new \DateTimeImmutable(sprintf('-%d days 9:00am', $i%14));
            $this->doKioskDropoff($em, $T, $collectedAt);

            $em->persist($T);
        }
    }

    /**
     * Tubes that have been checked-in by a Tech.
     *
     * These Tubes are ready to have Results made.
     */
    private function acceptedTubes(ObjectManager $em)
    {
        $numToCreate = 25;
        $checkedInBy = 'test-checkin-user';
        for ($i=1; $i<= $numToCreate; $i++) {
            $T = new Tube();

            $collectedAt = new \DateTimeImmutable(sprintf('-%d days 9:00am', $i%7));
            $this->doKioskDropoff($em, $T, $collectedAt);

            $T->markAccepted($checkedInBy, new \DateTimeImmutable(sprintf('-%d days 10:00am', $i%7)));

            $kitTypeNumber = ($i % 3) + 1;
            $T->setKitType('Example Type ' . $kitTypeNumber);

            // Tubes accepted at check-in will be added to a Well Plate
            $wellPlate = $this->findFixtureWellPlate();
            $T->addToWellPlate($wellPlate);

            $em->persist($T);
        }
    }

    /**
     * Tubes that have been rejected.
     */
    private function rejectedTubes(ObjectManager $em)
    {
        $numToCreate = 10;
        $checkedInBy = 'test-checkin-user';
        for ($i=1; $i<= $numToCreate; $i++) {
            $T = new Tube();

            $collectedAt = new \DateTimeImmutable(sprintf('-%d days 9:00am', $i%7));
            $this->doKioskDropoff($em, $T, $collectedAt);

            $T->markRejected($checkedInBy, new \DateTimeImmutable(sprintf('-%d days 10:00am', $i%7)));

            $kitTypeNumber = ($i % 3) + 1;
            $T->setKitType('Example Type ' . $kitTypeNumber);

            $em->persist($T);
        }
    }

    private function getRandomGroup(ObjectManager $em): ParticipantGroup
    {
        if (empty($this->allGroups)) {
            $this->allGroups = $em->getRepository(ParticipantGroup::class)->findAll();
        }

        return $this->allGroups[array_rand($this->allGroups)];
    }

    /**
     * All the work to simulate a kiosk dropoff.
     */
    private function doKioskDropoff(ObjectManager $em, Tube $tube, \DateTimeImmutable $collectedAt)
    {
        // Assume 1 tube per kiosk interaction
        $kiosk = $this->getReference('kiosk.Kiosk One');
        $kioskSession = new KioskSession($kiosk);
        $em->persist($kioskSession);

        $group = $this->getRandomGroup($em);
        $kioskSession->setParticipantGroup($group);

        $sessionTube = new KioskSessionTube($kioskSession, $tube, Tube::TYPE_SALIVA, $collectedAt);
        $kioskSession->addTubeData($sessionTube);

        $kioskSession->finish($this->specimenAccessionIdGen);
    }

    /**
     * Ensure fixture Tubes exist that match Tube IDs in example Tecan output
     * file at src/Resources/RPE1P7.XLS.
     */
    private function tubesForTecanExample(ObjectManager $em)
    {
        $repo = $em->getRepository(Tube::class);

        // Has Tube Accession IDs between 122 and 217.
        // This might re-use some of the "distributed" tubes from $this->distributedTubes()
        foreach(range(122, 217) as $i) {
            $accessionId = sprintf("T00000%d", $i);

            $T = $repo->findOneBy(['accessionId' => $accessionId]);
            if (!$T) {
                // Create with hardcoded Tube Accession ID
                $T = new Tube($accessionId);
            }

            // Drop-off
            $collectedAt = new \DateTimeImmutable(sprintf('-%d days 9:00am', 1));
            $this->doKioskDropoff($em, $T, $collectedAt);

            // Accepted
            $T->markAccepted('fixtures', new \DateTimeImmutable(sprintf('-%d days 10:00am', 1)));

            $em->persist($T);
        }

        $em->flush();
    }

    private function findFixtureWellPlate(): WellPlate
    {
        $referenceName = 'wellPlate.VIRALPLATE' . rand(1, 5);

        return $this->getReference($referenceName);
    }
}

<?php


namespace App\DataFixtures;


use App\Entity\Kiosk;
use App\Entity\DropOffSchedule;
use App\Scheduling\ScheduleCalculator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Times when users are able to drop off specimens
 */
class AppSiteDropOffScheduleFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
        foreach ($this->getData() as $raw) {
            $schedule = new DropOffSchedule($raw['label']);

            $schedule->setDaysOfTheWeek($raw['daysOfTheWeek']);
            $schedule->setNumExpectedDropOffsPerGroup($raw['numExpectedDropOffsPerGroup']);

            $schedule->setDailyStartTime($raw['dailyStartTime']);
            $schedule->setDailyEndTime($raw['dailyEndTime']);

            $schedule->setWindowIntervalMinutes($raw['windowIntervalMinutes'] ?? 30);

            $calculator = new ScheduleCalculator($schedule);
            // this is how the DropOffWindows get associated with the SiteDropOffSchedule
            // todo: better way to handle this
            $windows = $calculator->getWeeklyWindows();
            foreach ($windows as $window) {
                $manager->persist($window);
            }


            $manager->persist($schedule);
            if (isset($raw['referenceId'])) $this->addReference($raw['referenceId'], $schedule);
        }

        $manager->flush();
    }

    protected function getData()
    {
        return [
            [
                'referenceId' => 'SiteDropOffSchedule.default',
                'label' => 'Tues & Thurs 8am-5pm',
                'daysOfTheWeek' => ['TU', 'TH'],
                'numExpectedDropOffsPerGroup' => 2,
                'dailyStartTime' => new \DateTimeImmutable('09:00:00'),
                'dailyEndTime'   => new \DateTimeImmutable('17:00:00'),
            ],
        ];
    }
}
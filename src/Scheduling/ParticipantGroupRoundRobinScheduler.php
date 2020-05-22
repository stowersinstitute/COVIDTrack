<?php


namespace App\Scheduling;


use App\Entity\DropOffWindow;
use App\Entity\ParticipantGroup;
use App\Entity\SiteDropOffSchedule;

class ParticipantGroupRoundRobinScheduler
{
    /**
     * Assigns $groups to drop off windows in $schedule
     *
     *  1. The window with the least number of groups in it is used first
     *  2. In the case of a tie, the earliest window is used
     *
     * @param ParticipantGroup[]  $groups
     * @param SiteDropOffSchedule $schedule
     */
    public function assignByDays(array $groups, SiteDropOffSchedule $schedule)
    {
        $calculator = new ScheduleCalculator($schedule);

        $numToAssignPerGroup = $schedule->getNumExpectedDropOffsPerGroup();

        $windowsByDay = $calculator->getWindowsByWeekday();
        $days = array_keys($windowsByDay);

        foreach ($groups as $group) {
            $numAssigned = 0;

            while ($numAssigned < $numToAssignPerGroup) {
                foreach ($days as $day) {
                    $nextWindow = $this->getNextWindow($windowsByDay[$day]);
                    $group->addDropOffWindow($nextWindow);

                    $numAssigned++;
                    // Might need to immediately exit before finishing all days
                    if ($numAssigned >= $numToAssignPerGroup) break;
                }
            }
        }
    }


    protected function getNextWindow(array $availableWindows) : DropOffWindow
    {
        // Sort windows by number of groups in the window, then start time
        usort($availableWindows, function(DropOffWindow $a, DropOffWindow $b) {
            // Number of groups is the same, sort by time of day
            if ($a->getNumParticipantGroups() === $b->getNumParticipantGroups()) {
                return ($a->getStartsAt() < $b->getStartsAt()) ? -1 : 1;
            }

            return ($a->getNumParticipantGroups() < $b->getNumParticipantGroups()) ? -1 : 1;
        });

        return $availableWindows[0];
    }
}
<?php


namespace App\Entity;


use Doctrine\ORM\EntityRepository;

class DropOffScheduleRepository extends EntityRepository
{
    public function findDefaultSchedule() : ?DropOffSchedule
    {
        return $this->findOneBy([]);
    }
}
<?php


namespace App\Entity;


use Doctrine\ORM\EntityRepository;

class DropOffWindowRepository extends EntityRepository
{
    public function findOneByTimeSlotId($timeSlotId)
    {

    }
}
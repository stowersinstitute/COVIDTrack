<?php

namespace App\Form;

use App\Entity\CollectionEvent;
use App\Entity\ParticipantGroup;

class SpecimenFormData
{
    /**
     * @var ParticipantGroup
     */
    public $participantGroup;

    /**
     * @var CollectionEvent
     */
    public $collectionEvent;

    /**
     * @var \DateTime
     */
    public $collectedAt;

    /**
     * @var string
     */
    public $status;
}

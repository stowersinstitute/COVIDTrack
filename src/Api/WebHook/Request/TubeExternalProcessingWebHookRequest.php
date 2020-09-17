<?php

namespace App\Api\WebHook\Request;

use App\Entity\Tube;

/**
 * Report on Tubes sent for External Processing. Data sent to remote server via Web Hook.
 */
class TubeExternalProcessingWebHookRequest extends WebHookRequest
{
    /**
     * @var Tube[]
     */
    private $tubes = [];

    /**
     * @param Tube[] $tubes
     */
    public function __construct(array $tubes = [])
    {
        $this->setTubes($tubes);
    }

    public function setTubes(array $tubes): void
    {
        $this->tubes = [];

        foreach ($tubes as $tube) {
            $this->addTube($tube);
        }
    }

    public function addTube(Tube $T): void
    {
        if (!$T->getId()) {
            throw new \InvalidArgumentException('Tube must have an ID');
        }
        if (null === $T->getAccessionId()) {
            throw new \InvalidArgumentException('Tube must have an Accession ID');
        }
        if (null === $T->getParticipantGroup()) {
            throw new \InvalidArgumentException('Tube must have an associated Participant Group');
        }
        if (null === $T->getParticipantGroup()->getExternalId()) {
            throw new \InvalidArgumentException('Tube Participant Group must have an External ID');
        }

        $this->tubes[$T->getId()] = $T;
    }

    /**
     * @return Tube[]
     */
    public function getTubes(): array
    {
        return $this->tubes;
    }

    /**
     * Return data sent as WebHookRequest payload. Must be compatible with json_encode().
     *
     * @return mixed
     */
    public function getRequestData()
    {
        // Convert each Tube to an array of data sent to the API
        return array_map(function(Tube $T) {
            $group = $T->getParticipantGroup();

            return [
                'group_external_id' => $group ? $group->getExternalId() : null,
                'accession_id' => $T->getAccessionId(),
                'collected_at' => self::dateToRequestDataFormat($T->getCollectedAt()),
                'receipt_at' => self::dateToRequestDataFormat($T->getReturnedAt()),
                'external_processing_at' => self::dateToRequestDataFormat($T->getExternalProcessingAt()),
            ];
        }, array_values($this->tubes));
    }
}

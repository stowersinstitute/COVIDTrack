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

    /**
     * @throws \LogicException When Tube does not have all data to be sent to web hook
     */
    public function addTube(Tube $T): void
    {
        // Throws Exception if not ready. Tube requires specific data before
        // being sent to a Web Hook. This always prevents adding without that data.
        $T->verifyReadyForWebHook();

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
                'id' => $T->getId(),
                'group_external_id' => $group ? $group->getExternalId() : null,
                'accession_id' => $T->getAccessionId(),
                'collected_at' => self::dateToRequestDataFormat($T->getCollectedAt()),
                'receipt_at' => self::dateToRequestDataFormat($T->getReturnedAt()),
                'external_processing_at' => self::dateToRequestDataFormat($T->getExternalProcessingAt()),
            ];
        }, array_values($this->tubes));
    }
}

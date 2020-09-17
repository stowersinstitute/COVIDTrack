<?php

namespace App\Api\WebHook\Request;

use App\Entity\SpecimenResult;
use App\Entity\SpecimenResultAntibody;
use App\Entity\SpecimenResultQPCR;

/**
 * Report new Specimen Results to remote server via Web Hook.
 */
class NewResultsWebHookRequest extends WebHookRequest
{
    /**
     * @var SpecimenResult[]
     */
    private $results = [];

    /**
     * @param SpecimenResult[]
     */
    public function __construct(array $results = [])
    {
        $this->setResults($results);
    }

    public function setResults(array $results): void
    {
        $this->results = [];

        foreach ($results as $result) {
            $this->addResult($result);
        }
    }

    public function addResult(SpecimenResult $result): void
    {
        if (!$result->getId()) {
            throw new \InvalidArgumentException('SpecimenResultAntibody must have an ID');
        }

        $this->results[$result->getId()] = $result;
    }

    /**
     * @return SpecimenResult[]
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Return data sent as WebHookRequest payload. Must be compatible with json_encode().
     *
     * @return mixed
     */
    public function getRequestData()
    {
        // Convert each Specimen Result to an array of data sent to the API
        return array_map(function(SpecimenResult $r) {
            switch (get_class($r)) {
                case SpecimenResultAntibody::class:
                    $type = 'ANTIBODY';
                    break;
                case SpecimenResultQPCR::class:
                    $type = 'VIRAL';
                    break;
                default:
                    throw new \InvalidArgumentException('Unknown SpecimenResult class for building request type discriminator value');
            }

            $group = $r->getSpecimen()->getParticipantGroup();

            // NOTE: Adding fields below will require code that also sets
            // SpecimenResult.webHookStatus = SpecimenResult::WEBHOOK_STATUS_PENDING
            // when that field on the entity changes. See SpecimenResult->setConclusion()
            return [
                'id' => $r->getId(),
                'type' => $type,
                'result' => $r->getConclusion(),
                'published_at' => self::dateToRequestDataFormat($r->getCreatedAt()),
                'collected_at' => self::dateToRequestDataFormat($r->getSpecimenCollectedAt()),
                'group' => [
                    'id' => $group->getId(),
                    'external_id' => $group->getExternalId(),
                    'title' => $group->getTitle(),
                ],
            ];
        }, array_values($this->results));
    }
}

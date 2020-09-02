<?php

namespace App\Api\WebHook\Request;

use App\Entity\SpecimenResult;
use App\Entity\SpecimenResultAntibody;
use App\Entity\SpecimenResultQPCR;

/**
 * Report new Specimen Results to remote server via WebHook.
 */
class NewResultsWebHookRequest extends WebHookRequest
{
    /**
     * @var SpecimenResult[]
     */
    private $results = [];

    public function __construct(array $results = [])
    {
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

    public function getRequestData()
    {
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

            $publishedAt = $r->getCreatedAt()->setTimezone(new \DateTimeZone('UTC'));
            $iso8601 = 'Y-m-d\TH:i:s\Z';

            $group = $r->getSpecimen()->getParticipantGroup();

            // NOTE: Adding / Removing fields below may require
            // updating Gedmo\Timestampable() "field" list on property
            // SpecimenResult.webHookFieldChangedAt
            return [
                'id' => $r->getId(),
                'type' => $type,
                'conclusion' => $r->getConclusion(),
                'published_at' => $publishedAt->format($iso8601),
                'group' => [
                    'id' => $group->getId(),
                    'external_id' => $group->getExternalId(),
                    'title' => $group->getTitle(),
                ],
            ];
        }, array_values($this->results));
    }
}

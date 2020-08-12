<?php

namespace App\Api\WebHook\Request;

use App\Entity\SpecimenResultAntibody;

/**
 * Report new Antibody Results to remote server via WebHook.
 */
class NewAntibodyResultsWebHookRequest extends WebHookRequest
{
    /**
     * @var SpecimenResultAntibody[]
     */
    private $results = [];

    public function __construct(array $results = [])
    {
        foreach ($results as $result) {
            $this->addResult($result);
        }
    }

    public function addResult(SpecimenResultAntibody $result): void
    {
        if (!$result->getId()) {
            throw new \InvalidArgumentException('SpecimenResultAntibody must have an ID');
        }

        $this->results[$result->getId()] = $result;
    }

    public function getRequestData()
    {
        return array_map(function(SpecimenResultAntibody $r) {
            $publishedAt = $r->getCreatedAt()->setTimezone(new \DateTimeZone('UTC'));
            $iso8601 = 'Y-m-d\TH:i:s\Z';

            $group = $r->getSpecimen()->getParticipantGroup();

            return [
                'id' => $r->getId(),
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

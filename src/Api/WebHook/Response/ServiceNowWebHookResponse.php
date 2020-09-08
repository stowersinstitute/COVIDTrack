<?php

namespace App\Api\WebHook\Response;

use App\Entity\SpecimenResult;

/**
 * Response received from ServiceNow API.
 */
class ServiceNowWebHookResponse extends WebHookResponse
{
    const STATUS_COMPLETE = "COMPLETE";
    const STATUS_COMPLETE_WITH_ERRORS = "COMPLETE WITH ERRORS";
    const STATUS_ERROR = "ERROR";

    const ROW_STATUS_SUCCESS = "SUCCESS";
    const ROW_STATUS_IGNORED = "IGNORED";

    /**
     * Result of json_decode() on JSON string
     *
     * @var mixed
     */
    private $bodyAsJson;

    public function isCompletedWithoutErrors(): bool
    {
        $decoded = $this->getBodyAsDecodedJson();

        return $decoded['result']['status'] === self::STATUS_COMPLETE;
    }

    public function getResultMessage(): string
    {
        $decoded = $this->getBodyAsDecodedJson();

        return $decoded['result']['message'];
    }

    /**
     * Get row info (result of sending to ServiceNow import API) representing
     * each Specimen Result sent in the API Request.
     *
     * Expected keys:
     *
     * - status (string) "SUCCESS" when successfully received or "IGNORED" when an error (see "message" key)
     * - message (string) Friendly message explaining "status"
     * - data (array) Copy of data sent in the Response for this row
     *
     * @return array[]
     */
    public function getRows(): array
    {
        $decoded = $this->getBodyAsDecodedJson();

        return $decoded['result']['rows'];
    }

    /**
     * Get rows that completed successfully.
     *
     * @see getRows() for documented keys
     * @return array[]
     */
    public function getSuccessfulRows(): array
    {
        $errors = array_filter($this->getRows(), function (array $row) {
            return $row['status'] === self::ROW_STATUS_SUCCESS;
        });

        return $errors;
    }

    /**
     * Get rows that did not complete successfully.
     *
     * @see getRows() for documented keys
     * @return array[]
     */
    public function getUnsuccessfulRows(): array
    {
        $errors = array_filter($this->getRows(), function (array $row) {
            return $row['status'] !== self::ROW_STATUS_SUCCESS;
        });

        return $errors;
    }

    private function getBodyAsDecodedJson()
    {
        // Only parse once per instance
        if (null !== $this->bodyAsJson) {
            return $this->bodyAsJson;
        }

        $body = json_decode($this->getBodyContents(), true);
        if (null === $body) {
            throw new \RuntimeException(sprintf('Cannot parse JSON HTTP Response Body: %d %s', json_last_error(), json_last_error_msg()));
        }

        $this->bodyAsJson = $body;

        return $this->bodyAsJson;
    }

    /**
     * Update SpecimenResult records based on data in the Web Hook HTTP Response.
     *
     * @param SpecimenResult[] $resultsSentInRequest
     */
    public function updateResultWebHookStatus(array $resultsSentInRequest): void
    {
        /**
         * Indexed by SpecimenResult.id to allow efficient look up and removal below.
         *
         * @var SpecimenResult[] $resultsById
         */
        $resultsById = array_reduce($resultsSentInRequest, function(array $carry, SpecimenResult $r) {
            $carry[$r->getId()] = $r;

            return $carry;
        }, []);

        try {
            // Server Date from Response
            $timestamp = $this->getTimestamp();
        } catch (\Exception $e) {
            // Fall back to current PHP time.
            // A developer should probably update the Response parsing logic
            // to extract the Date.
            $timestamp = new \DateTimeImmutable();
        }

        // Update successful rows returned in Response
        foreach ($this->getSuccessfulRows() as $row) {
            if (empty($row['data'])) {
                throw new \InvalidArgumentException('Response data does not contain object key "data" for this row');
            }
            if (empty($row['data']['id'])) {
                throw new \InvalidArgumentException('Response data does not contain object key "data.id" for this row');
            }

            $id = $row['data']['id']; // "id" serialized in row in Request

            $result = $resultsById[$id] ?? null;
            if (empty($result)) {
                continue;
            } else {
                // Remove from index so not automatically updated below
                unset($resultsById[$id]);
            }

            $result->setWebHookSuccess($timestamp, $row['message']);
        }

        // Update unsuccessful rows returned in Response
        foreach ($this->getUnsuccessfulRows() as $row) {
            $id = $row['data']['id']; // "id" serialized in row in Request

            $result = $resultsById[$id] ?? null;
            if (empty($result)) {
                continue;
            } else {
                // Remove from index so not automatically updated below
                unset($resultsById[$id]);
            }

            $result->setWebHookError($timestamp, $row['message']);
        }

        // Any remaining results not in Response but originally submitted are
        // assumed to be positively reported.
        foreach ($resultsById as $result) {
            $result->setWebHookSuccess($timestamp, "Not explicitly present in Response. Assuming Success.");
        }
    }
}

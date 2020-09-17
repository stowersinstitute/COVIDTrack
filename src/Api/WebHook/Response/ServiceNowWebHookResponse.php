<?php

namespace App\Api\WebHook\Response;

use App\Entity\SpecimenResult;
use App\Entity\Tube;

/**
 * Response received from ServiceNow API.
 */
class ServiceNowWebHookResponse extends WebHookResponse
{
    /**
     * Response "status" returned when all submitted rows were successfully received
     * without any errors. Check method $this->getSuccessfulRows().
     */
    const STATUS_COMPLETE = "COMPLETE";

    /**
     * Response "status" returned when one or more rows was not successfully received.
     * Check method $this->getUnsuccessfulRows().
     */
    const STATUS_COMPLETE_WITH_ERRORS = "COMPLETE WITH ERRORS";

    /**
     * Response "status" returned when the server cannot parse the Request
     * or encounters an error.
     */
    const STATUS_ERROR = "ERROR";

    /**
     * Row "status" returned when row was successfully received without errors.
     * Rows with this status will return via method $this->getSuccessfulRows().
     */
    const ROW_STATUS_SUCCESS = "SUCCESS";

    /**
     * Row "status" returned when row could not be received due to an error.
     * Rows with this status will return via method $this->getUnsuccessfulRows().
     */
    const ROW_STATUS_IGNORED = "IGNORED";

    /**
     * Result of json_decode() on JSON string
     *
     * @var mixed
     */
    private $bodyAsJson;

    /**
     * Whether the API request was completed successfully. This means the
     * request was received and parsed correctly by the web hook URL.
     *
     * However this does not mean all rows were successfully received.
     * Individual rows may have issues. Check method $this->getUnsuccessfulRows().
     */
    public function isRequestSuccessful(): bool
    {
        $decoded = $this->getBodyAsDecodedJson();

        $successful = [
            self::STATUS_COMPLETE,
            self::STATUS_COMPLETE_WITH_ERRORS,
        ];

        return in_array($decoded['result']['status'], $successful);
    }

    /**
     * Get text describing the overall request status and whether it was
     * received or not.
     */
    public function getResultMessage(): string
    {
        $decoded = $this->getBodyAsDecodedJson();

        return $decoded['result']['message'];
    }

    /**
     * Get row info (result of sending to ServiceNow import API) representing
     * each record sent in the API Request.
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

        // "rows" not present in malformed request response
        return $decoded['result']['rows'] ?? [];
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
     * Update records based on data in the Web Hook HTTP Response.
     *
     * @param SpecimenResult|Tube[] $recordsSentInRequest
     */
    public function updateResultWebHookStatus(array $recordsSentInRequest): void
    {
        /**
         * Indexed by ID to allow efficient look up and removal below.
         *
         * @var SpecimenResult|Tube[] $recordsById
         */
        $recordsById = array_reduce($recordsSentInRequest, function(array $carry, object $r) {
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

            $record = $recordsById[$id] ?? null;
            if (empty($record)) {
                continue;
            } else {
                // Remove from index so not automatically updated below
                unset($recordsById[$id]);
            }

            $record->setWebHookSuccess($timestamp, $row['message']);
        }

        // Update unsuccessful rows returned in Response
        foreach ($this->getUnsuccessfulRows() as $row) {
            $id = $row['data']['id']; // "id" serialized in row in Request

            $record = $recordsById[$id] ?? null;
            if (empty($record)) {
                continue;
            } else {
                // Remove from index so not automatically updated below
                unset($recordsById[$id]);
            }

            $record->setWebHookError($timestamp, $row['message']);
        }

        // If Request was successful in general (and not an error / Exception)
        // assume any remaining records not explicitly present in Response
        // are positively reported.
        if ($this->isRequestSuccessful()) {
            foreach ($recordsById as $record) {
                $record->setWebHookSuccess($timestamp, "Not explicitly present in Response. Assuming Success.");
            }
        }
    }
}

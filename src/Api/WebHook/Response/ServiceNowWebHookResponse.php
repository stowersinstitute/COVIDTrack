<?php

namespace App\Api\WebHook\Response;

/**
 * Response received from ServiceNow API.
 */
class ServiceNowWebHookResponse extends WebHookResponse
{
    const STATUS_COMPLETE = "COMPLETE";
    const STATUS_COMPLETE_WITH_ERRORS = "COMPLETE WITH ERRORS";
    const STATUS_ERROR = "ERROR";

    const ROW_STATUS_IGNORED = "IGNORED";

    /**
     * Result of json_decode() on JSON string
     *
     * @var mixed
     */
    private $bodyAsJson;

    public function hasSuccessfulStatus(): bool
    {
        $decoded = $this->getBodyAsDecodedJson();

        return $decoded['result']['status'] === self::STATUS_COMPLETE;
    }

    public function hasErrorStatus(): bool
    {
        // Anything that isn't a known success status will consider an error status
        return false === $this->hasSuccessfulStatus();
    }

    /**
     * Get row data returned in the Response. Only present when errors occur.
     * Empty when submitted data is successfully received without errors.
     *
     * @return array[]
     */
    public function getRows(): array
    {
        $decoded = $this->getBodyAsDecodedJson();

        return $decoded['result']['rows'];
    }

    /**
     * Get error message returned for each row.
     *
     * @return string[]
     */
    public function getRowErrors(): array
    {
        $errors = array_map(function (array $row) {
            return $row['message'];
        }, $this->getRows());

        return $errors;
    }

    public function hasRowErrors(): bool
    {
        return count($this->getRowErrors()) > 0;
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
}

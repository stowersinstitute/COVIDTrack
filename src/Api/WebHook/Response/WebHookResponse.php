<?php

namespace App\Api\WebHook\Response;

use App\Entity\SpecimenResult;
use App\Entity\Tube;
use Psr\Http\Message\ResponseInterface;

/**
 * Response received from a WebHook API. Custom public methods to suit COVIDTrack application.
 */
class WebHookResponse
{
    /**
     * URL where WebHookRequest was submitted
     *
     * @var string
     */
    private $requestUrl;

    /**
     * PSR-7 HTTP Response
     * @var ResponseInterface
     */
    private $httpResponse;

    public function __construct(ResponseInterface $response, string $requestUrl)
    {
        $this->httpResponse = $response;
        $this->requestUrl = $requestUrl;
    }

    /**
     * Whether the API request was completed successfully. This means the
     * request was received and parsed correctly by the web hook URL.
     */
    public function isRequestSuccessful(): bool
    {
        return $this->getStatusCode() === 200;
    }

    public function getRawResponse(): ResponseInterface
    {
        return $this->httpResponse;
    }

    public function getRequestUrl(): string
    {
        return $this->requestUrl;
    }

    public function getStatusCode(): int
    {
        return $this->httpResponse->getStatusCode();
    }

    /**
     * Get timestamp when Response was returned, as indicated by Response metadata.
     *
     * @return \DateTimeImmutable
     * @throws \Exception When cannot parse a timestamp value from the Response
     */
    public function getTimestamp(): \DateTimeImmutable
    {
        $dateHeaders = $this->httpResponse->getHeader('Date');

        // Header values are always an array. Even when only 1 value.
        // See ResponseInterface->getHeader()
        $utcDateString = array_shift($dateHeaders);

        return new \DateTimeImmutable($utcDateString);
    }

    public function getReasonPhrase(): string
    {
        return $this->httpResponse->getReasonPhrase();
    }

    /**
     * Get Response Body content, such as the JSON payload returned.
     */
    public function getBodyContents(): string
    {
        return (string) $this->httpResponse->getBody();
    }

    /**
     * Returns all headers. Looks like below example, which can represent
     * multiple values for each header. Don't blame me this is the PSR-7 way:
     *
     *     [
     *         'Date' => [
     *             'Mon, 07 Sep 2020 16:07:50 GMT',
     *         ],
     *         'Example-Multiple-Header' => [
     *             'Value1',
     *             'Value2',
     *             'Value3',
     *         ],
     *     ]
     *
     *
     * @return string[][]
     */
    public function getHeaders(): array
    {
        return $this->httpResponse->getHeaders();
    }

    /**
     * Update records based on data in the Web Hook HTTP Response.
     *
     * @param SpecimenResult|Tube[] $recordsSentInRequest
     */
    public function updateResultWebHookStatus(array $recordsSentInRequest): void
    {
        try {
            // Server Date from Response
            $timestamp = $this->getTimestamp();
        } catch (\Exception $e) {
            // Fall back to current PHP time.
            // A developer should probably update the Response parsing logic
            // to extract the Date.
            $timestamp = new \DateTimeImmutable();
        }

        // Assume all results positively reported if request was successful
        if ($this->isRequestSuccessful()) {
            foreach ($recordsSentInRequest as $result) {
                $result->setWebHookSuccess($timestamp, "Not explicitly present in Response. Assuming Success.");
            }
        }
    }
}

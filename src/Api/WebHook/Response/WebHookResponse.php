<?php

namespace App\Api\WebHook\Response;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

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
     * @return string[]
     */
    public function getHeaders(): array
    {
        return $this->httpResponse->getHeaders();
    }
}

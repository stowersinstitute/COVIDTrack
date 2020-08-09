<?php

namespace App\Api\ServiceNow;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Response received from ServiceNow API. Custom public methods to suit COVIDTrack application.
 */
class Response
{
    /**
     * URL where Request was submitted
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
    }

    public function getRawResponse(): ResponseInterface
    {
        return $this->httpResponse;
    }

    public function getStatusCode(): int
    {
        return $this->httpResponse->getStatusCode();
    }

    public function getBody(): StreamInterface
    {
        return $this->httpResponse->getBody();
    }

    /**
     * @return string[]
     */
    public function getHeaders(): array
    {
        return $this->httpResponse->getHeaders();
    }
}

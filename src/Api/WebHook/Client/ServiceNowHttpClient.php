<?php

namespace App\Api\WebHook\Client;

use App\Api\WebHook\Response\ServiceNowWebHookResponse;
use App\Api\WebHook\Response\WebHookResponse;
use Psr\Http\Message\ResponseInterface;

/**
 * WebHook API client for reporting Antibody Results.
 *
 * Subclassed to make Symfony auto-wiring services easier.
 */
class ServiceNowHttpClient extends HttpClient
{
    /**
     * Build Response
     */
    protected function buildWebHookResponse(ResponseInterface $clientResponse): WebHookResponse
    {
        return new ServiceNowWebHookResponse($clientResponse, $this->url);
    }
}

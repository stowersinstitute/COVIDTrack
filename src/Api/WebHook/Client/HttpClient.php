<?php

namespace App\Api\WebHook\Client;

use App\Api\WebHook\Request\WebHookRequest;
use App\Api\WebHook\Response\WebHookResponse;
use App\Entity\WebHookLog;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Establish an HTTP connection to the WebHook API.
 *
 * Supports Basic Auth with username and password.
 */
class HttpClient
{
    /** @var string */
    protected $url;

    /** @var string */
    protected $username;

    /** @var string */
    protected $password;

    /**
     * Unique identifier for this client session.
     *
     * @var string
     */
    protected $lifecycleId;

    /**
     * @var null|\GuzzleHttp\Client
     */
    protected $client;

    /**
     * Supported options:
     *
     * - url (string) https://subdomain.domain.com:port/
     * - username (string)
     * - password (string)
     */
    protected $constructorOptions;

    /**
     * Whether constructorOptions have been initialized yet.
     *
     * @var bool
     */
    protected $constructorOptionsInitialized = false;

    /**
     * Logs API interactions.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * See config/services.yaml for where $options are set based on
     * environment variables.
     */
    public function __construct(array $options, LoggerInterface $logger = null)
    {
        $this->constructorOptions = $options;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Submit a GET HTTP request and return its WebHookResponse.
     *
     * @param array $options  Request options to apply. See \GuzzleHttp\RequestOptions.
     */
    public function get(WebHookRequest $request, array $options = []): WebHookResponse
    {
        return $this->request('GET', $request, $options);
    }

    /**
     * Submit a POST HTTP request and return its WebHookResponse.
     *
     * @param array $options  Request options to apply. See \GuzzleHttp\RequestOptions.
     */
    public function post(WebHookRequest $request, array $options = []): WebHookResponse
    {
        return $this->request('POST', $request, $options);
    }

    /**
     * Submit an HTTP request and return its response.
     *
     * @param string         $method   GET|POST
     * @param WebHookRequest $request
     * @param array          $options  Request options to apply. See \GuzzleHttp\RequestOptions.
     */
    protected function request(string $method, WebHookRequest $request, array $options = []): WebHookResponse
    {
        $this->initConstructorOptions($this->constructorOptions);

        // This is where the JSON is actually set to be the Request Body
        $options[RequestOptions::BODY] = $request->toJson();

        // Request data logged to entity WebHookLog
        $this->logRequest($method, $request, $options);

        try {
            // Actually send the Request
            $clientResponse = $this->getClient()->request($method, $this->url, $options);
            $response = $this->buildWebHookResponse($clientResponse);
        } catch (\Exception $e) {
            $this->logException($e);
            throw $e;
        }

        // Response data logged to entity WebHookLog
        $this->logResponse($response);

        return $response;
    }

    /**
     * Create HTTP client to communicate with a WebHook API.
     */
    protected function getClient(): Client
    {
        if (!empty($this->client)) return $this->client;

        $this->initConstructorOptions($this->constructorOptions);

        $this->client = new Client([
            'cookies' => false,
            'timeout' => 30, // seconds
            'auth' => [$this->username, $this->password], // Basic Auth
            'headers' => [
                // All API response from WebHook should use JSON
                'Accept' => 'application/json',
            ],
        ]);

        return $this->client;
    }

    /**
     * Doing constructor parsing in a separate method allows this class
     * to be injected as a service without causing errors where the environment
     * might not be properly setup for WebHook integration.
     */
    protected function initConstructorOptions(array $options)
    {
        if ($this->constructorOptionsInitialized) return;

        $resolver = new OptionsResolver();
        $resolver->setRequired([
            'url',
            'username',
            'password',
        ]);
        $resolver->setAllowedTypes('url', 'string');
        $resolver->setAllowedTypes('username', 'string');
        $resolver->setAllowedTypes('password', 'string');

        // TODO: Log config errors like empty URL, empty USERNAME, empty PW
        $options = $resolver->resolve($options);

        $this->url = $options['url'];
        $this->username = $options['username'];
        $this->password = $options['password'];

        // UNIX time with current microseconds should be almost certainly unique
        $now = new \DateTimeImmutable();
        $this->lifecycleId = $now->format('U-u');

        $this->constructorOptionsInitialized = true;
    }

    /**
     * @param array $options Keys are from \Guzzle\RequestOptions
     */
    protected function logRequest(string $method, WebHookRequest $request, array $options)
    {
        $context = array_merge(
            // See \Guzzle\RequestOptions
            $options,

            // These array keys are just private identifiers for easier viewing in log.
            // Prefixed with "_" so they don't conflict with \Guzzle\RequestOptions
            [
                WebHookLog::CONTEXT_LIFECYCLE_ID_KEY => $this->lifecycleId,
                '_REQUEST_CLASS' => get_class($request),
                '_HTTP_METHOD' => $method,
                '_URL' => $this->url,
                '_JSON_BODY' => $options[RequestOptions::BODY], // See \Guzzle\RequestOptions
            ]
        );

        $this->logger->debug('Sending Request.', $context);
    }

    /**
     * Write Response data to logger, which writes a database entity WebHookLog
     */
    protected function logResponse(WebHookResponse $response)
    {
        // These array keys are just private identifiers for easier viewing in log.
        // Prefixed with "_" so they don't conflict with \Guzzle\RequestOptions
        $context = [
            WebHookLog::CONTEXT_LIFECYCLE_ID_KEY => $this->lifecycleId,
            '_RESPONSE_CLASS' => get_class($response),
            '_STATUS_CODE' => $response->getStatusCode(),
            '_STATUS_REASON' => $response->getReasonPhrase(),
            '_HEADERS' => $response->getHeaders(),
            '_JSON_BODY' => $response->getBodyContents(),
        ];

        $this->logger->debug('Response Received.', $context);
    }

    protected function logException(\Exception $e)
    {
        $this->logger->emergency('Exception', [
            WebHookLog::CONTEXT_LIFECYCLE_ID_KEY => $this->lifecycleId,
            'EXCEPTION_CODE' => $e->getCode(),
            'EXCEPTION_MESSAGE' => $e->getMessage(),
            'EXCEPTION_TRACE' => $e->getTraceAsString(),
        ]);
    }

    /**
     * Build Response
     */
    protected function buildWebHookResponse(ResponseInterface $clientResponse): WebHookResponse
    {
        return new WebHookResponse($clientResponse, $this->url);
    }
}

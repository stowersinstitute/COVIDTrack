<?php

namespace App\Api\WebHook\Client;

use App\Api\WebHook\Request\WebHookRequest;
use App\Api\WebHook\Response\WebHookResponse;
use GuzzleHttp\Client;
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
     * See config/services.yaml for where $options are set based on
     * environment variables.
     */
    public function __construct(array $options)
    {
        $this->constructorOptions = $options;
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

        $options['body'] = $request->toJson();

        $response = $this->getClient()->request($method, $this->url, $options);

        return new WebHookResponse($response, $this->url);
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
        $resolver = new OptionsResolver();
        $resolver->setRequired([
            'url',
            'username',
            'password',
        ]);
        $resolver->setAllowedTypes('url', 'string');
        $resolver->setAllowedTypes('username', 'string');
        $resolver->setAllowedTypes('password', 'string');

        $options = $resolver->resolve($options);

        $this->url = $options['url'];
        $this->username = $options['username'];
        $this->password = $options['password'];
    }
}

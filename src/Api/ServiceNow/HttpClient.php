<?php

namespace App\Api\ServiceNow;

use App\Api\ServiceNow\Request\Request;
use GuzzleHttp\Client;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Establish an HTTP connection to the ServiceNow API.
 *
 * Supports Basic Auth with username and password.
 */
class HttpClient
{
    /** @var string */
    private $url;

    /** @var string */
    private $username;

    /** @var string */
    private $password;

    /**
     * @var null|\GuzzleHttp\Client
     */
    private $client;

    /**
     * Supported options:
     *
     * - url (string) https://subdomain.domain.com:port/
     * - username (string)
     * - password (string)
     */
    private $constructorOptions;

    /**
     * See config/services.yaml for where $options are set based on
     * environment variables.
     */
    public function __construct(array $options)
    {
        $this->constructorOptions = $options;
    }

    /**
     * Submit a GET HTTP request and return its Response.
     *
     * @param string  $uri      URL part after the base URL e.g. "/path/to/something"
     * @param Request $request
     * @param array   $options  Request options to apply. See \GuzzleHttp\RequestOptions.
     */
    public function get(string $uri, Request $request, array $options = []): Response
    {
        return $this->request($uri, 'GET', $request, $options);
    }

    /**
     * Submit a POST HTTP request and return its Response.
     *
     * @param string  $uri      URL part after the base URL e.g. "/path/to/something"
     * @param Request $request
     * @param array   $options  Request options to apply. See \GuzzleHttp\RequestOptions.
     */
    public function post(string $uri, Request $request, array $options = []): Response
    {
        return $this->request($uri, 'POST', $request, $options);
    }

    /**
     * Submit an HTTP request and return its response.
     *
     * @param string  $uri      URL part after the base URL e.g. "/path/to/something"
     * @param string  $method   GET|POST
     * @param Request $request
     * @param array   $options  Request options to apply. See \GuzzleHttp\RequestOptions.
     */
    private function request(string $uri, string $method, Request $request, array $options = []): Response
    {
        $this->initConstructorOptions($this->constructorOptions);

        $requestUrl = $this->url . $uri;

        $options['body'] = $request->toJson();

        $response = $this->getClient()->request($method, $requestUrl, $options);

        return new Response($response, $requestUrl);
    }

    /**
     * Create HTTP client to communicate with ServiceNow.
     */
    private function getClient(): Client
    {
        if (!empty($this->client)) return $this->client;

        $this->initConstructorOptions($this->constructorOptions);

        $this->client = new Client([
            'cookies' => false,
            'timeout' => 30, // seconds
            'auth' => [$this->username, $this->password], // Basic Auth
            'headers' => [
                // All API response from ServiceNow in JSON
                'Accept' => 'application/json',
            ],
        ]);

        return $this->client;
    }

    /**
     * Doing constructor parsing in a separate method allows this class
     * to be injected as a service without causing errors where the environment
     * might not be properly setup for ServiceNow integration.
     */
    private function initConstructorOptions(array $options)
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

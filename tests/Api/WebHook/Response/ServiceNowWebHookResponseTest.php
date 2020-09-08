<?php

namespace App\Tests\Api\WebHook\Response;

use App\Api\WebHook\Response\ServiceNowWebHookResponse;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class ServiceNowWebHookResponseTest extends TestCase
{
    public function testGetTimestamp()
    {
        $mockBody = file_get_contents(__DIR__ . '/responses/successful-with-errors-mixed-rows.json');

        $httpResponse = $this->buildMockHttpResponse(200, $mockBody);
        $response = new ServiceNowWebHookResponse($httpResponse, '/does/not/matter');

        $this->assertInstanceOf(\DateTimeImmutable::class, $response->getTimestamp());
    }

    public function testParsesRowsFromMixedResult()
    {
        $mockBody = file_get_contents(__DIR__ . '/responses/successful-with-errors-mixed-rows.json');

        $httpResponse = $this->buildMockHttpResponse(200, $mockBody);
        $response = new ServiceNowWebHookResponse($httpResponse, '/does/not/matter');

        $this->assertFalse($response->isCompletedWithoutErrors());

        $this->assertCount(5, $response->getUnsuccessfulRows());
        $this->assertCount(4, $response->getSuccessfulRows());
    }

    public function testParsesRowsFromFullySuccessfulResult()
    {
        $mockBody = file_get_contents(__DIR__ . '/responses/successful-all-rows.json');

        $httpResponse = $this->buildMockHttpResponse(200, $mockBody);
        $response = new ServiceNowWebHookResponse($httpResponse, '/does/not/matter');

        $this->assertTrue($response->isCompletedWithoutErrors());

        $this->assertCount(0, $response->getUnsuccessfulRows());
        $this->assertCount(3, $response->getSuccessfulRows());
    }

    public function testMalformedRequest()
    {
        $this->markTestIncomplete('Need example of failing Response body');

        $mockBody = file_get_contents('');

        $httpResponse = $this->buildMockHttpResponse(500, $mockBody);
        $response = new ServiceNowWebHookResponse($httpResponse, '/does/not/matter');

        // Do the rows show errors?
//        $this->assertTrue($response->hasUnsuccessfulRows());
//        $this->assertCount(1, $response->getUnsuccessfulRows());
    }

    /**
     * Create an HTTP Response object. Mocks that an HTTP Request was executed
     * and a Response was received.
     *
     * @return ResponseInterface
     */
    private function buildMockHttpResponse(int $statusCode, string $body)
    {
        $headers = [
            "X-Is-Logged-In" => "true",
            "X-Transaction-ID" => "abb30fe51b8f",
            "Pragma" => "no-store,no-cache",
            "Cache-control" => "no-cache,no-store,must-revalidate,max-age=-1",
            "Expires" => "0",
            "Content-Type" => "application/json;charset=UTF-8",
            "Transfer-Encoding" => "chunked",
            "Date" => "Fri, 04 Sep 2020 16:24:06 GMT",
            "Server" => "ServiceNow",
            "Set-Cookie" => "JSESSIONID=BBE29D9406305288287927054992D18F; Path=/; HttpOnly; SameSite=None; Secure, glide_user=; Max-Age=0; Expires=Thu, 01-Jan-1970 00:00:10 GMT; Path=/; HttpOnly; SameSite=None; Secure, glide_user_session=; Max-Age=0; Expires=Thu, 01-Jan-1970 00:00:10 GMT; Path=/; HttpOnly; SameSite=None; Secure, glide_user_route=glide.bddc6d3d7e479b65aaab78a452f8a4e0; Max-Age=2147483647; Expires=Wed, 22-Sep-2088 19:38:13 GMT; Path=/; HttpOnly; SameSite=None; Secure, glide_session_store=67B30FE51B8F141009A0DD3BDC4BCB99; Max-Age=1800; Expires=Fri, 04-Sep-2020 16:54:06 GMT; Path=/; HttpOnly; SameSite=None; Secure, BIGipServerpool_abcdefdev=2760267786.36414.0000; path=/; Httponly; Secure; SameSite=None; Secure",
            "Strict-Transport-Security" => "max-age=63072000; includeSubDomains",
        ];

        return new Response($statusCode, $headers, $body);
    }
}

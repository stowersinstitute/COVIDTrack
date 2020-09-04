<?php

namespace App\Tests\Api\WebHook\Response;

use App\Api\WebHook\Response\ServiceNowWebHookResponse;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class ServiceNowWebHookResponseTest extends TestCase
{
    public function testWithRecordErrors()
    {
        $mockBody = <<<MOCKBODY
{"result":{"status":"COMPLETE WITH ERRORS","message":"Encountered errors with 1 or more rows","rows":[{"status":"IGNORED","message":"Could not locate group id","data":{"id":242,"type":"ANTIBODY","conclusion":"NEGATIVE","published_at":"2020-09-03T15:37:41Z","group":{"id":24,"external_id":"abcdefghijklmnopqrstuvwxyz654321","title":"Individual 1"}}},{"status":"IGNORED","message":"Could not locate group id","data":{"id":243,"type":"ANTIBODY","conclusion":"NEGATIVE","published_at":"2020-09-02T15:37:41Z","group":{"id":24,"external_id":"abcdefghijklmnopqrstuvwxyz654321","title":"Individual 1"}}},{"status":"IGNORED","message":"Could not locate group id","data":{"id":244,"type":"ANTIBODY","conclusion":"NEGATIVE","published_at":"2020-09-01T15:37:41Z","group":{"id":24,"external_id":"abcdefghijklmnopqrstuvwxyz654321","title":"Individual 1"}}},{"status":"IGNORED","message":"Could not locate group id","data":{"id":157,"type":"VIRAL","conclusion":"NEGATIVE","published_at":"2020-09-03T15:37:35Z","group":{"id":24,"external_id":"abcdefghijklmnopqrstuvwxyz654321","title":"Individual 1"}}},{"status":"IGNORED","message":"Could not locate group id","data":{"id":158,"type":"VIRAL","conclusion":"NEGATIVE","published_at":"2020-09-02T15:37:36Z","group":{"id":24,"external_id":"abcdefghijklmnopqrstuvwxyz654321","title":"Individual 1"}}},{"status":"IGNORED","message":"Could not locate group id","data":{"id":159,"type":"VIRAL","conclusion":"NON-NEGATIVE","published_at":"2020-09-01T15:37:36Z","group":{"id":24,"external_id":"abcdefghijklmnopqrstuvwxyz654321","title":"Individual 1"}}}]}}
MOCKBODY;

        $httpResponse = $this->buildMockHttpResponse(200, $mockBody);
        $response = new ServiceNowWebHookResponse($httpResponse, '/does/not/matter');

        $this->assertFalse($response->hasSuccessfulStatus());
        $this->assertTrue($response->hasErrorStatus());

        $this->assertTrue($response->hasRowErrors());
        $this->assertCount(6, $response->getRowErrors());
    }

    public function testWithPartialRecordErrors()
    {
        $this->markTestSkipped('Need HTTP data');

        $mockBody = <<<MOCKBODY
MOCKBODY;

        $httpResponse = $this->buildMockHttpResponse(200, $mockBody);
        $response = new ServiceNowWebHookResponse($httpResponse, '/does/not/matter');

        $this->assertFalse($response->hasSuccessfulStatus());
        $this->assertTrue($response->hasErrorStatus());

        $this->assertTrue($response->hasRowErrors());
        $this->assertCount(6, $response->getRowErrors());
    }

    public function testMalformedRequest()
    {
        $this->markTestSkipped('Need HTTP data');

        $mockBody = <<<MOCKBODY
MOCKBODY;

        $httpResponse = $this->buildMockHttpResponse(200, $mockBody);
        $response = new ServiceNowWebHookResponse($httpResponse, '/does/not/matter');

        $this->assertFalse($response->hasSuccessfulStatus());
        $this->assertTrue($response->hasErrorStatus());
        // Do the rows show errors?
//        $this->assertTrue($response->hasRowErrors());
//        $this->assertCount(1, $response->getRowErrors());
    }

    public function testSuccessfulRequest()
    {
        $mockBody = <<<MOCKBODY
{"result":{"status":"COMPLETE","message":"Successfully imported all rows","rows":[]}}
MOCKBODY;

        $httpResponse = $this->buildMockHttpResponse(200, $mockBody);
        $response = new ServiceNowWebHookResponse($httpResponse, '/does/not/matter');

        $this->assertTrue($response->hasSuccessfulStatus());
        $this->assertFalse($response->hasErrorStatus());
        $this->assertFalse($response->hasRowErrors());
        $this->assertCount(0, $response->getRowErrors());
    }

    /**
     * Create an HTTP Response object. Mocks that an HTTP Request was executed
     * and a Response was received.
     *
     * @return \PHPUnit\Framework\MockObject\MockObject|ResponseInterface
     */
    private function buildMockHttpResponse(int $statusCode, string $body)
    {
        $response = $this->getMockBuilder(ResponseInterface::class)->getMock();

        // Status Code
        $response->method('getStatusCode')->willReturn($statusCode);

        // Body
        $mockStream = $this->getMockBuilder(StreamInterface::class)->getMock();
        $mockStream->method('__toString')->willReturn($body);
        $response->method('getBody')->willReturn($mockStream);

        // Headers
        $response->method('getHeaders')->willReturn([
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
        ]);

        return $response;
    }
}

<?php

namespace EnMarche\MailerBundle\Tests\Client;

use EnMarche\MailerBundle\Client\MailClient;
use EnMarche\MailerBundle\Client\MailRequestInterface;
use EnMarche\MailerBundle\Client\PayloadFactoryInterface;
use EnMarche\MailerBundle\Test\DummyMailRequest;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class MailClientTest extends TestCase
{
    /**
     * @var MockObject|ClientInterface
     */
    private $client;

    /**
     * @var MockObject|PayloadFactoryInterface
     */
    private $payloadFactory;

    /**
     * @var MailClient
     */
    private $mailClient;

    protected function setUp()
    {
        $this->client = $this->createMock(ClientInterface::class);
        $this->payloadFactory = $this->createMock(PayloadFactoryInterface::class);

        $this->mailClient = new MailClient($this->client, $this->payloadFactory);
    }

    protected function tearDown()
    {
        $this->client = null;
        $this->payloadFactory = null;
        $this->mailClient = null;
    }

    public function testSend(): void
    {
        $requestPayload = ['request_payload'];
        $mailRequest = $this->getMailRequest();

        $this->payloadFactory->expects($this->once())
            ->method('createRequestPayload')
            ->with($mailRequest)
            ->willReturn($requestPayload)
        ;

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200)
        ;

        $this->client->expects($this->once())
            ->method('request')
            ->with('POST', '', ['body' => json_encode($requestPayload)])
            ->willReturn($response)
        ;

        $responsePayload = ['ok'];

        $this->payloadFactory->expects($this->once())
            ->method('createResponsePayload')
            ->with($response)
            ->willReturn($responsePayload)
        ;

        $this->mailClient->send($mailRequest);

        $this->assertSame($requestPayload, $mailRequest->getRequestPayload());
        $this->assertSame($responsePayload, $mailRequest->getResponsePayload());
    }

    public function testResend(): void
    {
        $requestPayload = ['request_payload'];
        $mailRequest = $this->getMailRequest($requestPayload);

        $this->payloadFactory->expects($this->never())
            ->method('createRequestPayload')
        ;

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200)
        ;

        $this->client->expects($this->once())
            ->method('request')
            ->with('POST', '', ['body' => json_encode($requestPayload)])
            ->willReturn($response)
        ;

        $responsePayload = ['ok'];

        $this->payloadFactory->expects($this->once())
            ->method('createResponsePayload')
            ->with($response)
            ->willReturn($responsePayload)
        ;

        $this->mailClient->send($mailRequest);

        $this->assertSame($requestPayload, $mailRequest->getRequestPayload());
        $this->assertSame($responsePayload, $mailRequest->getResponsePayload());
    }

    /**
     * @expectedException \EnMarche\MailerBundle\Exception\InvalidMailResponseException
     * @expectedExceptionMessage Invalid response (code: 500) for mail request (id: 1):
     *                           "error happens"
     */
    public function testInvalidResponse(): void
    {
        $requestPayload = ['request_payload'];
        $mailRequest = $this->getMailRequest($requestPayload);

        $this->payloadFactory->expects($this->never())
            ->method('createRequestPayload')
        ;

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->any())
            ->method('getStatusCode')
            ->willReturn(500)
        ;
        $response->expects($this->once())
            ->method('getBody')
            ->willReturn('error happens')
        ;

        $this->client->expects($this->once())
            ->method('request')
            ->with('POST', '', ['body' => json_encode($requestPayload)])
            ->willReturn($response)
        ;

        $this->payloadFactory->expects($this->never())
            ->method('createResponsePayload')
        ;

        $this->mailClient->send($mailRequest);
    }

    private function getMailRequest(array $requestPayload = null): MailRequestInterface
    {
        return new class($requestPayload) extends DummyMailRequest {
            public $id = 1;

            public function __construct(array $requestPayload = null)
            {
                $this->requestPayload = $requestPayload;
            }
        };
    }
}

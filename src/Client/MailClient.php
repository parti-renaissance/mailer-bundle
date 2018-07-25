<?php

namespace EnMarche\MailerBundle\Client;

use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\Request;

class MailClient implements MailClientInterface
{
    private $client;
    private $payloadFactory;

    public function __construct(ClientInterface $client, PayloadFactoryInterface $payloadFactory)
    {
        $this->client = $client;
        $this->payloadFactory = $payloadFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function send(MailRequestInterface $mailRequest): void
    {
        $requestPayload = $mailRequest->getRequestPayload();

        if (!$requestPayload) {
            $requestPayload = $this->payloadFactory->createRequestPayload($mailRequest);

            $mailRequest->setRequestPayload($requestPayload);
        }

        $response = $this->client->request(Request::METHOD_POST, '' , [
            'body' => $requestPayload,
        ]);

        $mailRequest->setResponsePayload($response);
    }
}

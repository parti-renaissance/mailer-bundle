<?php

namespace EnMarche\MailerBundle\Client;

use EnMarche\MailerBundle\Exception\InvalidMailResponseException;
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
        // maybe a retry
        $requestPayload = $mailRequest->getRequestPayload();

        if (!$requestPayload) {
            $requestPayload = $this->payloadFactory->createRequestPayload($mailRequest);

            $mailRequest->prepare($requestPayload);
        }

        $response = $this->client->request(Request::METHOD_POST, '', [
            'body' => json_encode($requestPayload),
        ]);

        if ($response->getStatusCode() >= 300) {
            throw new InvalidMailResponseException($mailRequest, $response);
        }

        $mailRequest->deliver($this->payloadFactory->createResponsePayload($response));
    }
}

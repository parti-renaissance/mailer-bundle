<?php

namespace EnMarche\MailerBundle\Tests\Test;

use EnMarche\MailerBundle\Client\MailRequestInterface;
use EnMarche\MailerBundle\Entity\Address;
use Ramsey\Uuid\UuidInterface;

class DummyMailRequest implements MailRequestInterface
{
    protected $id;
    protected $app = 'test';
    protected $type = 'fake';
    protected $replyTo;
    protected $templateName = 'dummy';
    protected $templateVars = [];
    protected $recipientVars = [];
    protected $ccRecipients = [];
    protected $bccRecipients = [];
    protected $campaign;
    protected $requestPayload;
    protected $responsePayload;

    /**
     * {@inheritdoc}
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getApp(): string
    {
        return $this->app;
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function getReplyTo(): ?Address
    {
        return $this->replyTo;
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplateName(): string
    {
        return $this->templateName;
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplateVars(): array
    {
        return $this->templateVars;
    }

    /**
     * {@inheritdoc}
     */
    public function getRecipientVars(): iterable
    {
        return $this->recipientVars;
    }

    /**
     * {@inheritdoc}
     */
    public function getRecipientsCount(): int
    {
        return \count($this->recipientVars);
    }

    /**
     * {@inheritdoc}
     */
    public function hasCopyRecipients(): bool
    {
        return \count($this->ccRecipients) || \count($this->bccRecipients);
    }

    /**
     * {@inheritdoc}
     */
    public function getCcRecipients(): iterable
    {
        return $this->ccRecipients;
    }

    /**
     * {@inheritdoc}
     */
    public function getBccRecipients(): iterable
    {
        return $this->bccRecipients;
    }

    /**
     * {@inheritdoc}
     */
    public function getCampaign(): ?UuidInterface
    {
        return $this->campaign;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestPayload(): ?array
    {
        return $this->requestPayload;
    }

    /**
     * {@inheritdoc}
     */
    public function setRequestPayload(array $payload): void
    {
        $this->requestPayload = $payload;
    }

    /**
     * {@inheritdoc}
     */
    public function getResponsePayload(): ?array
    {
        return $this->responsePayload;
    }

    /**
     * {@inheritdoc}
     */
    public function setResponsePayload(array $payload): void
    {
        $this->responsePayload = $payload;
    }
}

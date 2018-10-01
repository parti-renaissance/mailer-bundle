<?php

namespace EnMarche\MailerBundle\Test;

use EnMarche\MailerBundle\Client\MailRequestInterface;
use EnMarche\MailerBundle\Entity\Address;
use Ramsey\Uuid\UuidInterface;

class DummyMailRequest implements MailRequestInterface
{
    protected $id;
    protected $app = 'test';
    protected $type = 'fake';
    protected $replyTo;
    protected $senderName;
    protected $senderEmail;
    protected $templateName = 'dummy';
    protected $templateVars = [];
    protected $recipientVars = [];
    protected $ccRecipients = [];
    protected $bccRecipients = [];
    protected $campaign;
    protected $createdAt;
    protected $deliveredAt;
    protected $requestPayload;
    protected $responsePayload;
    protected $subject;

    public function __construct(\DateTimeImmutable $createdAt = null)
    {
        $this->createdAt = $createdAt ?: new \DateTimeImmutable();
    }

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
    public function getSenderName(): ?string
    {
        return $this->senderName;
    }

    /**
     * {@inheritdoc}
     */
    public function getSenderEmail(): ?string
    {
        return $this->senderEmail;
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
    public function getCcRecipients(): array
    {
        return $this->ccRecipients;
    }

    /**
     * {@inheritdoc}
     */
    public function getBccRecipients(): array
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
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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
    public function prepare(array $requestPayload): void
    {
        $this->requestPayload = $requestPayload;
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
    public function deliver(array $responsePayload, \DateTimeImmutable $deliveredAt = null): void
    {
        $this->responsePayload = $responsePayload;
        $this->deliveredAt = $deliveredAt ?: new \DateTimeImmutable();
    }

    /**
     * {@inheritdoc}
     */
    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubject(): ?string
    {
        return $this->subject;
    }
}

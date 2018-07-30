<?php

namespace EnMarche\MailerBundle\Tests\Test;

use EnMarche\MailerBundle\Mail\MailInterface;
use EnMarche\MailerBundle\Mail\RecipientInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class DummyMail implements MailInterface
{
    protected $app = 'test';
    protected $toRecipients = [];
    protected $ccRecipients = [];
    protected $bccRecipients = [];
    protected $replyTo;
    protected $templateVars = [];
    protected $templateName = 'dummy';
    protected $type = 'fake';
    protected $createdAt;
    protected $chunkId;

    public function __construct(\DateTimeImmutable $createdAt = null)
    {
        $this->createdAt = $createdAt ?: new \DateTimeImmutable();
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
    public function getToRecipients(): array
    {
        return $this->toRecipients;
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
    public function hasCopyRecipients(): bool
    {
        return $this->ccRecipients || $this->bccRecipients;
    }

    /**
     * {@inheritdoc}
     */
    public function getReplyTo(): ?RecipientInterface
    {
        return $this->replyTo;
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
    public function getTemplateName(): string
    {
        return $this->templateName;
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
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function getChunkId(): ?UuidInterface
    {
        return $this->chunkId;
    }

    /**
     * {@inheritdoc}
     */
    public function chunk(int $size = -1): iterable
    {
        $this->chunkId = Uuid::uuid4();

        foreach (array_chunk($this->toRecipients, $size) as $chunk) {
            $mail = clone $this;
            $mail->toRecipients = $chunk;

            yield $mail;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(): string
    {
        return \serialize($this);
    }
}

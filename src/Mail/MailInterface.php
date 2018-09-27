<?php

namespace EnMarche\MailerBundle\Mail;

use Ramsey\Uuid\UuidInterface;

interface MailInterface
{
    public function getApp(): string;

    /**
     * @return RecipientInterface[]
     */
    public function getToRecipients(): array;

    /**
     * @return RecipientInterface[]
     */
    public function getCcRecipients(): array;

    /**
     * @return RecipientInterface[]
     */
    public function getBccRecipients(): array;

    /**
     * @return bool Whether the mail has cc or bcc recipients
     */
    public function hasCopyRecipients(): bool;

    public function getReplyTo(): ?RecipientInterface;

    public function getSender(): ?SenderInterface;

    public function getSubject(): ?string;

    /**
     * The common vars for campaign emails
     *
     * @return string[]
     */
    public function getTemplateVars(): array;

    public function getTemplateName(): string;

    public function getCreatedAt(): \DateTimeImmutable;

    /**
     * @return string One of Mail constants
     */
    public function getType(): string;

    /**
     * Allows to identify all chunks from a same original mail.
     */
    public function getChunkId(): ?UuidInterface;

    /**
     * Create clone containing the provided size of recipients.
     *
     * @return static[]|iterable Passing -1 means no chunk
     */
    public function chunk(int $size = -1): iterable;

    public function serialize(): string;
}

<?php

namespace EnMarche\MailerBundle\Mail;

use Ramsey\Uuid\UuidInterface;

interface MailInterface extends \Serializable
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

    public function getReplyTo(): ?RecipientInterface;

    /**
     * The common vars for campaign emails
     *
     * @return string[]
     */
    public function getTemplateVars(): array;

    public function getTemplateName(): string;

    /**
     * @return string One of Mail constants
     */
    public function getType(): string;

    public function getChunkId(): ?UuidInterface;

    /**
     * Create clone containing the provided size of recipients.
     *
     * @return static[]|iterable Passing -1 means no chunk
     */
    public function chunk(int $size = -1): iterable;
}

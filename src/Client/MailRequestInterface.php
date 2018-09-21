<?php

namespace EnMarche\MailerBundle\Client;

use EnMarche\MailerBundle\Entity\Address;
use EnMarche\MailerBundle\Entity\RecipientVars;
use Ramsey\Uuid\UuidInterface;

interface MailRequestInterface
{
    public function getId(): ?int;

    public function getApp(): string;

    public function getType(): string;

    public function getReplyTo(): ?Address;

    public function getTemplateName(): string;

    /**
     * @return string[]
     */
    public function getTemplateVars(): array;

    /**
     * @return RecipientVars[]|iterable
     */
    public function getRecipientVars(): iterable;

    public function getRecipientsCount(): int;

    /**
     * @return bool true if a request has Cc or Bcc fields
     */
    public function hasCopyRecipients(): bool;

    /**
     * @return Address[]|iterable
     */
    public function getCcRecipients(): iterable;

    /**
     * @return Address[]|iterable
     */
    public function getBccRecipients(): iterable;

    public function getCampaign(): ?UuidInterface;

    public function getCreatedAt(): \DateTimeImmutable;

    public function getRequestPayload(): ?array;

    public function prepare(array $requestPayload): void;

    public function getResponsePayload(): ?array;

    public function deliver(array $responsePayload): void;

    public function getDeliveredAt(): ?\DateTimeImmutable;
}

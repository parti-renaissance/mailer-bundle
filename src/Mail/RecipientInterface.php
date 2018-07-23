<?php

namespace EnMarche\MailerBundle\Mail;

use Ramsey\Uuid\UuidInterface;

interface RecipientInterface
{
    public function getName(): ?string;

    public function getEmail(): string;

    /**
     * @return string[]
     */
    public function getTemplateVars(): array;

    public function getChunkId(): ?UuidInterface;

    /**
     * @internal
     */
    public function setChunkId(UuidInterface $uuid): void;
}

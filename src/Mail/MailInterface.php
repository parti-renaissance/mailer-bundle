<?php

namespace EnMarche\MailerBundle\Mail;

interface MailInterface extends \JsonSerializable
{
    public function getRecipients(): array;

    public function setRecipients(array $recipients): self;

    public function getSubject(): string;

    public function setSubject(string $subject): self;

    public function getFromName(): string;

    public function setFromName(string $fromName): self;

    public function getFromEmail(): string;

    public function setFromEmail(string $fromEmail): self;

    public function getTemplateKey(): string;

    public function setTemplateKey(string $templateKey): self;

    public function getTemplateVars(): array;

    public function setTemplateVars(array $templateVars): self;
}

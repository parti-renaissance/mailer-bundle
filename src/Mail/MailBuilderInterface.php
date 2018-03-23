<?php

namespace EnMarche\MailerBundle\Mail;

interface MailBuilderInterface
{
    public function setMailClassName(string $mailClassName): self;

    public function addRecipient(RecipientInterface $recipient): self;

    public function setRecipients(array $recipients): self;

    public function setFromName(string $fromName): self;

    public function setFromEmail(string $fromEmail): self;

    public function setTemplateKey(string $templateKey): self;

    public function setTemplateVars(array $templateVars): self;

    public function build(): MailInterface;
}

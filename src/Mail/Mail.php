<?php

namespace EnMarche\MailerBundle\Mail;

class Mail implements MailInterface
{
    protected $fromName;
    protected $fromEmail;
    protected $templateKey;
    protected $recipients;
    protected $subject;
    protected $templateVars = [];

    public function getFromName(): string
    {
        return $this->fromName;
    }

    public function setFromName(string $fromName): MailInterface
    {
        $this->fromName = $fromName;

        return $this;
    }

    public function getFromEmail(): string
    {
        return $this->fromEmail;
    }

    public function setFromEmail(string $fromEmail): MailInterface
    {
        $this->fromEmail = $fromEmail;

        return $this;
    }

    public function getTemplateKey(): string
    {
        return $this->templateKey;
    }

    public function setTemplateKey(string $templateKey): MailInterface
    {
        $this->templateKey = $templateKey;

        return $this;
    }

    public function getRecipients(): array
    {
        return $this->recipients;
    }

    public function setRecipients(array $recipients): MailInterface
    {
        $this->recipients = $recipients;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): MailInterface
    {
        $this->subject = $subject;

        return $this;
    }

    public function getTemplateVars(): array
    {
        return $this->templateVars;
    }

    public function setTemplateVars(array $templateVars): MailInterface
    {
        $this->templateVars = $templateVars;

        return $this;
    }

    public function jsonSerialize()
    {
        return [
            'fromName' => $this->getFromName(),
            'fromEmail' => $this->getFromEmail(),
            'subject' => $this->getSubject(),
            'templateKey' => $this->getTemplateKey(),
            'templateVars' => $this->getTemplateVars(),
            'recipients' => $this->getRecipients(),
        ];
    }
}

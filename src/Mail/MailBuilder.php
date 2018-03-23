<?php

namespace EnMarche\MailerBundle\Mail;

use EnMarche\MailerBundle\Exception\InvalidMailClassException;

class MailBuilder implements MailBuilderInterface
{
    private $recipients = [];
    private $templateVars = [];
    private $fromName;
    private $fromEmail;
    private $mailClassName = Mail::class;
    private $subject;
    private $templateKey;

    public function __construct(string $fromEmail = '', string $fromName = '')
    {
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
    }

    public function setMailClassName(string $mailClassName): MailBuilderInterface
    {
        if (!is_a($mailClassName, MailInterface::class, true)) {
            throw new InvalidMailClassException(
                sprintf('Mail class "%s" must be one instance of "%s".', $mailClassName, MailInterface::class)
            );
        }

        $this->mailClassName = $mailClassName;

        return $this;
    }

    public function setFromName(string $fromName): MailBuilderInterface
    {
        $this->fromName = $fromName;

        return $this;
    }

    public function setFromEmail(string $fromEmail): MailBuilderInterface
    {
        $this->fromEmail = $fromEmail;

        return $this;
    }

    public function addRecipient(RecipientInterface $recipient): MailBuilderInterface
    {
        $this->recipients[] = $recipient;

        return $this;
    }

    public function setRecipients(array $recipients): MailBuilderInterface
    {
        $this->recipients = [];

        foreach ($recipients as $recipient) {
            $this->addRecipient($recipient);
        }

        return $this;
    }

    public function setTemplateVars(array $templateVars): MailBuilderInterface
    {
        $this->templateVars = $templateVars;

        return $this;
    }

    public function setTemplateKey(string $templateKey): MailBuilderInterface
    {
        $this->templateKey = $templateKey;

        return $this;
    }

    public function build(): MailInterface
    {
        /** @var MailInterface $mail */
        $mail = new $this->mailClassName();
        $mail->setRecipients($this->recipients);

        if ($this->fromName) {
            $mail->setFromName($this->fromName);
        }

        if ($this->fromEmail) {
            $mail->setFromEmail($this->fromEmail);
        }

        if ($this->subject) {
            $mail->setSubject($this->subject);
        }

        if ($this->templateVars) {
            $mail->setTemplateVars($this->templateVars);
        }

        if ($this->templateKey) {
            $mail->setTemplateKey($this->templateKey);
        }

        // Clears the recipients after mail creation
        $this->recipients = [];

        return $mail;
    }
}

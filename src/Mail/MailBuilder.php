<?php

namespace EnMarche\MailerBundle\Mail;

use EnMarche\MailerBundle\Exception\InvalidMailClassException;

class MailBuilder extends Mail implements MailBuilderInterface
{
    private $mailClass;
    private $toRecipients;
    private $ccRecipients = [];
    private $bccRecipients = [];
    private $replyTo;
    private $templateVars = [];

    /**
     * {@inheritdoc}
     */
    public function addToRecipient(RecipientInterface $recipient): MailBuilderInterface
    {
        $this->toRecipients[$recipient->getEmail()] = $recipient;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function removeToRecipient(RecipientInterface $recipient): MailBuilderInterface
    {
        unset($this->toRecipients[$recipient->getEmail()]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setToRecipients(array $recipients): MailBuilderInterface
    {
        $this->toRecipients = [];

        foreach ($recipients as $recipient) {
            $this->addToRecipient($recipient);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function resetToRecipients(): array
    {
        $recipients = $this->toRecipients;
        $this->toRecipients = [];

        return $recipients;
    }

    /**
     * {@inheritdoc}
     */
    public function addCcRecipient(RecipientInterface $recipient): MailBuilderInterface
    {
        $this->ccRecipients[$recipient->getEmail()] = $recipient;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function removeCcRecipient(RecipientInterface $recipient): MailBuilderInterface
    {
        unset($this->ccRecipients[$recipient->getEmail()]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setCcRecipients(array $recipients): MailBuilderInterface
    {
        $this->ccRecipients = [];

        foreach ($recipients as $recipient) {
            $this->addCcRecipient($recipient);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addBccRecipient(RecipientInterface $recipient): MailBuilderInterface
    {
        $this->bccRecipients[$recipient->getEmail()] = $recipient;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function removeBccRecipient(RecipientInterface $recipient): MailBuilderInterface
    {
        unset($this->bccRecipients[$recipient->getEmail()]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setBccRecipients(array $recipients): MailBuilderInterface
    {
        $this->bccRecipients = [];

        foreach ($recipients as $recipient) {
            $this->addBccRecipient($recipient);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setReplyTo(?RecipientInterface $replyTo): MailBuilderInterface
    {
        $this->replyTo = $replyTo;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addTemplateVar(string $name, string $value): MailBuilderInterface
    {
        $this->templateVars[$name] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function removeTemplateVar(string $name): MailBuilderInterface
    {
        unset($this->templateVars[$name]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setTemplateVars(array $templateVars): MailBuilderInterface
    {
        $this->templateVars = $templateVars;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getMail(): MailInterface
    {
        return new $this->mailClass(
            $this->getApp(),
            $this->resetToRecipients(),
            $this->replyTo,
            $this->ccRecipients,
            $this->bccRecipients,
            $this->templateVars
        );
    }

    /**
     * {@inheritdoc}
     */
    public static function create(string $mailClass, string $app): MailBuilderInterface
    {
        if (!is_subclass_of($mailClass, Mail::class)) {
            throw new InvalidMailClassException($mailClass);
        }

        $builder = new self($app, []);
        $builder->mailClass = $mailClass;

        return $builder;
    }
}

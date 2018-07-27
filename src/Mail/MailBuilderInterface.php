<?php

namespace EnMarche\MailerBundle\Mail;

use EnMarche\MailerBundle\Exception\InvalidMailClassException;

interface MailBuilderInterface extends MailInterface
{
    public function addToRecipient(RecipientInterface $recipient): self;

    public function removeToRecipient(RecipientInterface $recipient): self;

    /**
     * @param RecipientInterface[] $recipients
     */
    public function setToRecipients(array $recipients): self;

    /**
     * @return RecipientInterface[] Previous recipients
     */
    public function resetToRecipients(): array;

    public function addCcRecipient(RecipientInterface $recipient): self;

    public function removeCcRecipient(RecipientInterface $recipient): self;

    /**
     * @param RecipientInterface[] $recipients
     */
    public function setCcRecipients(array $recipients): self;

    public function addBccRecipient(RecipientInterface $recipient): self;

    public function removeBccRecipient(RecipientInterface $recipient): self;

    /**
     * @param RecipientInterface[] $recipients
     */
    public function setBccRecipients(array $recipients): self;

    public function setReplyTo(?RecipientInterface $recipient): self;

    public function addTemplateVar(string $name, string $value): self;

    public function removeTemplateVar(string $name): self;

    /**
     * @param string[] $templateVars
     */
    public function setTemplateVars(array $templateVars): self;

    /**
     * This method should clear "to" recipients to prevent
     * duplicates and ease sending chunks
     */
    public function getMail(): MailInterface;

    /**
     * @throws InvalidMailClassException
     */
    public static function create(string $mailClass, string $app): self;
}

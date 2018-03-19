<?php

namespace EnMarche\MailerBundle\Mail;

interface MailInterface extends \JsonSerializable
{
    /**
     * Returns the email's receivers.
     *
     * @return array
     */
    public function getReceivers(): array;

    /**
     * Returns the email's subject.
     *
     * @return string
     */
    public function getSubject(): string;

    /**
     * Returns the email's body content.
     *
     * @return string
     */
    public function getBody(): string;
}

<?php

namespace EnMarche\MailerBundle\Mailer;

use EnMarche\MailerBundle\Exception\InvalidMailException;
use EnMarche\MailerBundle\Mail\MailInterface;

class Mailer implements MailerInterface
{
    private $transporter;

    public function __construct(TransporterInterface $transporter)
    {
        $this->transporter = $transporter;
    }

    public function send(MailInterface $message): void
    {
        if (!$message->hasToRecipients()) {
            throw new InvalidMailException('No recipients.');
        }

        $this->transporter->transport($message);
    }
}

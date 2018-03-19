<?php

namespace EnMarche\MailerBundle\Mail;

class Mail implements MailInterface
{
    private $receivers;
    private $subject;
    private $body;

    public function __construct(array $receivers, string $subject, string $body)
    {
        $this->receivers = $receivers;
        $this->subject = $subject;
        $this->body = $body;
    }

    public function getReceivers(): array
    {
        return $this->receivers;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function jsonSerialize()
    {
        return [
            'receivers' => $this->getReceivers(),
            'subject' => $this->getSubject(),
            'body' => $this->getBody(),
        ];
    }
}

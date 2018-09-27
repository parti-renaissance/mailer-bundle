<?php

namespace EnMarche\MailerBundle\Mail;

class Sender implements SenderInterface
{
    private $email;
    private $name;

    final public function __construct(?string $email, ?string $name)
    {
        if (null === $email && null === $name) {
            throw new \InvalidArgumentException('Email or Name must be filled');
        }

        $this->email = $email;
        $this->name = $name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
}

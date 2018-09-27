<?php

namespace EnMarche\MailerBundle\Mail;

interface SenderInterface
{
    public function getEmail(): ?string;

    public function getName(): ?string;
}

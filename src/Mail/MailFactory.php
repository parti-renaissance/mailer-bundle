<?php

namespace EnMarche\MailerBundle\Mail;

abstract class MailFactory
{
    public static function create(array $receivers, string $subject, string $body): MailInterface
    {
        return new Mail($receivers, $subject, $body);
    }
}

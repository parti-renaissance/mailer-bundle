<?php

namespace EnMarche\MailerBundle\Mailer\Transporter;

final class TransporterType
{
    public const AMQP = 'amqp';

    public const ALL = [
        self::AMQP,
    ];

    public static function isValid(string $type): bool
    {
        return \in_array($type, self::ALL, true);
    }

    private function __construct()
    {
    }
}

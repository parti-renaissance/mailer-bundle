<?php

namespace EnMarche\MailerBundle\Mailer;

use EnMarche\MailerBundle\Transporter\AmqpMailTransporter;

final class TransporterType
{
    public const AMQP = 'amqp';

    public const CLASSES = [
        self::AMQP => AmqpMailTransporter::class,
    ];

    public static function isValid(string $type)
    {
        return \in_array($type, self::CLASSES, true);
    }

    private function __construct()
    {
    }
}

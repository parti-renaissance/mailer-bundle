<?php

namespace EnMarche\MailerBundle\Mailer;

use EnMarche\MailerBundle\Transporter\AmqpMailTransporter;

final class TransporterType
{
    public const AMQP = 'amqp';

    public const CLASSES = [
        self::AMQP => AmqpMailTransporter::class,
    ];

    private function __construct()
    {
    }
}

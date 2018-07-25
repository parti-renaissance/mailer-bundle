<?php

namespace EnMarche\MailerBundle\Transporter;

final class TransporterType
{
    public const AMQP = 'amqp';

    public const CLASSES = [
        self::AMQP => AmqpMailTransporter::class,
    ];
}

<?php

namespace EnMarche\MailerBundle\Transporter;

final class TransporterType
{
    public const RMQ = 'rmq';

    private static $all = [
        self::RMQ,
    ];

    public static function getAll(): array
    {
        return self::$all;
    }
}

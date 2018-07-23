<?php

namespace EnMarche\MailerBundle\Client\PayloadFactory;

final class PayloadType
{
    public const MAILJET = 'mailjet';

    public const ALL = [
        self::MAILJET,
    ];

    public const API_SETTINGS_MAP = [
        self::MAILJET => [
            'base_uri' => 'https://api.mailjet.com/v3/',
            'headers' => ['Content-Type' => 'application/json'],
        ],
    ];

    private function __construct()
    {
    }
}

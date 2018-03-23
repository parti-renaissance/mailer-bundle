<?php

namespace EnMarche\MailerBundle\Mail;

interface RecipientInterface extends \JsonSerializable
{
    public function getName(): string;

    public function getEmail(): string;

    public function getTemplateVars(): array;
}

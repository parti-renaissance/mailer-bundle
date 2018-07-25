<?php

namespace EnMarche\MailerBundle\Mail;

interface RecipientInterface
{
    public function getName(): ?string;

    public function getEmail(): string;

    /**
     * @return string[]
     */
    public function getTemplateVars(): array;
}

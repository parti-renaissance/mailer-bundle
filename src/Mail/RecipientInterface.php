<?php

namespace EnMarche\MailerBundle\Mail;

interface RecipientInterface
{
    public function getName(): ?string;

    public function getEmail(): string;

    /**
     * @return string[] The vars used to populate the template for that recipient
     */
    public function getTemplateVars(): array;
}

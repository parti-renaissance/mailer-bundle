<?php

namespace EnMarche\MailerBundle\Mail;

class Recipient implements RecipientInterface
{
    protected $name;
    protected $email;
    protected $templateVars;

    public function __construct(string $email, string $name = null, array $templateVars = [])
    {
        $this->name = $name;
        $this->email = $email;

        foreach ($templateVars as $varName => $varValue) {
            if ('' !== (string) $varName && '' !== (string) $varValue) {
                $this->templateVars[$varName] = $varValue;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplateVars(): array
    {
        return $this->templateVars;
    }
}

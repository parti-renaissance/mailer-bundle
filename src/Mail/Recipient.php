<?php

namespace EnMarche\MailerBundle\Mail;

class Recipient implements RecipientInterface
{
    private $name;
    private $email;
    private $templateVars;

    public function __construct(string $email, string $name = null, array $templateVars = [])
    {
        $this->name = $name;
        $this->email = $email;

        foreach ($templateVars as $var) {
            if ('' !== (string) $var) {
                $this->templateVars[] = $var;
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

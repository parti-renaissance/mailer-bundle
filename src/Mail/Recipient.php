<?php

namespace EnMarche\MailerBundle\Mail;

class Recipient implements RecipientInterface
{
    protected $name;
    protected $email;
    protected $templateVars;

    public function __construct(string $email, string $name = null, array $templateVars = [])
    {
        $this->email = $email;
        $this->name = $name ? MailUtils::escapeHtml($name) : null;
        $this->templateVars = MailUtils::validateTemplateVars($templateVars);
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

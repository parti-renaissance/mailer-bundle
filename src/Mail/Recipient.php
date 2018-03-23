<?php

namespace EnMarche\MailerBundle\Mail;

class Recipient implements RecipientInterface
{
    private $email;
    private $name;
    private $templateVars = [];

    public function __construct(string $email, string $name, array $templateVars = [])
    {
        $this->email = $email;
        $this->name = $name;
        $this->templateVars = $templateVars;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName($name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getTemplateVars(): array
    {
        return $this->templateVars;
    }

    public function setTemplateVars(array $templateVars): self
    {
        $this->templateVars = $templateVars;

        return $this;
    }

    public function jsonSerialize()
    {
        return [
            'name' => $this->getName(),
            'email' => $this->getEmail(),
            'templateVars' => $this->getTemplateVars(),
        ];
    }
}

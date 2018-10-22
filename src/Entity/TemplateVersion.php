<?php

namespace EnMarche\MailerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity
 * @ORM\Table(name="mailer_template_versions")
 */
class TemplateVersion
{
    /**
     * @var int
     *
     * @ORM\Column(type="integer", options={"unsigned": true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var UuidInterface
     *
     * @ORM\Column(type="uuid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    private $uuid;

    /**
     * @var Template
     *
     * @ORM\ManyToOne(targetEntity="EnMarche\MailerBundle\Entity\Template", inversedBy="versions")
     * @ORM\JoinColumn(nullable=false)
     */
    private $template;

    /**
     * @var string
     *
     * @ORM\Column
     */
    private $hash;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    public function __construct(string $hash)
    {
        $this->hash = $hash;
        $this->createdAt = new \DateTime();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUuid(): ?UuidInterface
    {
        return $this->uuid;
    }

    public function getTemplate(): Template
    {
        return $this->template;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setTemplate(Template $template): void
    {
        $this->template = $template;
    }
}

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
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var UuidInterface
     *
     * @ORM\Column(type="uuid")
     */
    private $uuid;

    /**
     * @var string
     *
     * @ORM\Column(type="text")
     */
    private $body;

    /**
     * @var string
     *
     * @ORM\Column(type="text")
     */
    private $subject;

    /**
     * @var Template
     *
     * @ORM\ManyToOne(targetEntity="EnMarche\MailerBundle\Entity\Template", inversedBy="versions", cascade={"all"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $template;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    public function __construct(UuidInterface $uuid, string $body, string $subject)
    {
        $this->uuid = $uuid;
        $this->body = $body;
        $this->subject = $subject;
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

    public function getBody(): string
    {
        return $this->body;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getTemplate(): Template
    {
        return $this->template;
    }

    public function getHash(): string
    {
        return hash('sha256', $this->body . $this->subject);
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

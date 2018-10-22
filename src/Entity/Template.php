<?php

namespace EnMarche\MailerBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="EnMarche\MailerBundle\Repository\TemplateRepository")
 * @ORM\Table(name="mailer_templates", indexes={
 *     @ORM\Index(columns={"app_name", "mail_class"})
 * })
 */
class Template
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
     * @var string
     *
     * @ORM\Column
     */
    private $appName;

    /**
     * @var string
     *
     * @ORM\Column
     */
    private $mailClass;

    /**
     * @var string
     *
     * @ORM\Column
     */
    private $mailType;

    /**
     * @var TemplateVersion
     *
     * @ORM\OneToMany(targetEntity="EnMarche\MailerBundle\Entity\TemplateVersion", mappedBy="template", cascade={"persist"})
     * @ORM\JoinColumn(onDelete="CASCADE")
     */
    private $versions;

    /**
     * @var TemplateVersion
     *
     * @ORM\OneToOne(targetEntity="EnMarche\MailerBundle\Entity\TemplateVersion", fetch="EAGER")
     */
    private $lastVersion;

    public function __construct(string $appName, string $mailClass, string $mailType)
    {
        $this->appName = $appName;
        $this->mailClass = $mailClass;
        $this->mailType = $mailType;
        $this->versions = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getAppName(): string
    {
        return $this->appName;
    }

    public function getMailClass(): string
    {
        return $this->mailClass;
    }

    public function getMailType(): string
    {
        return $this->mailType;
    }

    public function getVersions(): Collection
    {
        return $this->versions;
    }

    public function addVersion(TemplateVersion $version): void
    {
        if (!$this->versions->contains($version)) {
            $version->setTemplate($this);
            $this->versions->add($version);
        }
    }

    public function removeVersion(TemplateVersion $version): void
    {
        $this->versions->removeElement($version);
    }

    public function setLastVersion(TemplateVersion $version): void
    {
        $this->addVersion($this->lastVersion = $version);
    }

    public function getLastVersion(): TemplateVersion
    {
        return $this->lastVersion;
    }
}

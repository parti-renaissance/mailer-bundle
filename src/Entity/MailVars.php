<?php

namespace EnMarche\MailerBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

/**
 * Holds metadata that can be shared between mails from a same campaign.
 *
 * @ORM\Entity(repositoryClass="EnMarche\MailerBundle\Repository\MailVarsRepository")
 * @ORM\Table(
 *     name="mail_vars",
 *     indexes={
 *         @ORM\Index(name="app_idx", columns={"app"}),
 *         @ORM\Index(name="type_idx", columns={"type"}),
 *         @ORM\Index(name="campaign_idx", columns={"campaign"})
 *     }
 * )
 */
class MailVars
{
    /**
     * @var int|null
     *
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned": true})
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column
     */
    private $app;

    /**
     * @var string
     *
     * @ORM\Column
     */
    private $type;

    /**
     * @var Address|null
     *
     * @ORM\ManyToOne(targetEntity="Address", cascade={"persist", "refresh"})
     */
    private $replyTo;

    /**
     * @var string
     *
     * @ORM\Column
     */
    private $templateName;

    /**
     * @var string[]
     *
     * @ORM\Column(type="json_array")
     */
    private $templateVars;

    /**
     * @var Address[]|Collection
     *
     * @ORM\ManyToMany(targetEntity="Address", cascade={"persist", "refresh"})
     * @ORM\JoinTable(name="mails_cc")
     */
    private $ccRecipients;

    /**
     * @var Address[]|Collection
     *
     * @ORM\ManyToMany(targetEntity="Address", cascade={"persist", "refresh"})
     * @ORM\JoinTable(name="mails_bcc")
     */
    private $bccRecipients;

    /**
     * @var UuidInterface|null
     *
     * @ORM\Column(type="uuid", nullable=true, unique=true)
     */
    private $campaign;

    /**
     * @var \DateTimeInterface
     *
     * @ORM\Column(type="datetime_immutable")
     */
    private $createdAt;

    /**
     * @param string[]     $templateVars
     * @param Address[]    $ccRecipients
     * @param Address[]    $bccRecipients
     */
    public function __construct(
        string $app,
        string $type,
        string $templateName,
        Address $replyTo = null,
        array $templateVars = [],
        array $ccRecipients = [],
        array $bccRecipients = [],
        UuidInterface $campaign = null,
        \DateTimeImmutable $createdAt = null
    )
    {
        $this->app = $app;
        $this->type = $type;
        $this->replyTo = $replyTo;
        $this->templateName = $templateName;
        $this->templateVars = $templateVars;
        $this->ccRecipients = new ArrayCollection();
        foreach ($ccRecipients as $ccRecipient) {
            if (!$ccRecipient instanceof Address) {
                throw new \InvalidArgumentException(\sprintf(
                    'Expected an instance of "%s", but got "%s".',
                    Address::class,
                    is_object($ccRecipient) ? get_class($ccRecipient) : gettype($ccRecipient)
                ));
            }
            $this->ccRecipients->add($ccRecipient);
        }
        $this->bccRecipients = new ArrayCollection();
        foreach ($bccRecipients as $bccRecipient) {
            if (!$bccRecipient instanceof Address) {
                throw new \InvalidArgumentException(\sprintf(
                    'Expected an instance of "%s", but got "%s".',
                    Address::class,
                    is_object($bccRecipient) ? get_class($bccRecipient) : gettype($bccRecipient)
                ));
            }
            $this->bccRecipients->add($bccRecipient);
        }
        $this->campaign = $campaign;
        $this->createdAt = $createdAt ?: new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getApp(): string
    {
        return $this->app;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getReplyTo(): ?Address
    {
        return $this->replyTo;
    }

    public function getTemplateName(): string
    {
        return $this->templateName;
    }

    /**
     * @return string[]
     */
    public function getTemplateVars(): array
    {
        return $this->templateVars;
    }

    /**
     * @return Address[]|Collection
     */
    public function getCcRecipients(): Collection
    {
        return $this->ccRecipients;
    }

    /**
     * @return Address[]|Collection
     */
    public function getBccRecipients(): Collection
    {
        return $this->bccRecipients;
    }

    public function getCampaign(): ?UuidInterface
    {
        return $this->campaign;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}

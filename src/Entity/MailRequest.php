<?php

namespace EnMarche\MailerBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use EnMarche\MailerBundle\Client\MailRequestInterface;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity(repositoryClass="EnMarche\MailerBundle\Repository\MailRequestRepository")
 * @ORM\Table(
 *     name="mailer_mail_requests",
 *     indexes={
 *         @ORM\Index(name="chunk_campaign_idx", columns={"campaign"})
 *     }
 * )
 */
class MailRequest implements MailRequestInterface
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
     * @var MailVars
     *
     * @ORM\ManyToOne(targetEntity="MailVars", cascade={"persist", "refresh"})
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private $vars;

    /**
     * @var RecipientVars[]|Collection
     *
     * @ORM\OneToMany(targetEntity="RecipientVars", cascade={"all"}, mappedBy="mailRequest")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private $recipientVars;

    /**
     * @var UuidInterface|null
     *
     * @ORM\Column(type="uuid", nullable=true)
     */
    private $campaign;

    /**
     * @var array|null
     *
     * @ORM\Column(type="json_array", nullable=true)
     */
    private $requestPayload;

    /**
     * @var array|null
     *
     * @ORM\Column(type="json_array", nullable=true)
     */
    private $responsePayload;

    /**
     * @var \DateTimeImmutable|null
     *
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $deliveredAt;

    /**
     * @param RecipientVars[] $recipientVars
     */
    public function __construct(MailVars $vars, array $recipientVars)
    {
        $this->vars = $vars;
        $this->recipientVars = new ArrayCollection();
        foreach ($recipientVars as $recipient) {
            if (!$recipient instanceof RecipientVars) {
                throw new \InvalidArgumentException(\sprintf(
                    'Expected an instance of "%s", but got %s".',
                    RecipientVars::class,
                    is_object($recipient) ? get_class($recipient) : gettype($recipient)
                ));
            }
            $this->recipientVars->add($recipient);
            $recipient->setMailRequest($this);
        }
        $this->campaign = $vars->getCampaign(); // duplication to perform concurrency check
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getApp(): string
    {
        return $this->vars->getApp();
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return $this->vars->getType();
    }

    /**
     * {@inheritdoc}
     *
     * @return RecipientVars[]|Collection
     */
    public function getRecipientVars(): iterable
    {
        return $this->recipientVars;
    }

    /**
     * {@inheritdoc}
     */
    public function getRecipientsCount(): int
    {
        return $this->recipientVars->count();
    }

    public function hasCopyRecipients(): bool
    {
        return $this->vars->getCcRecipients()->count() || $this->vars->getBccRecipients()->count();
    }

    /**
     * {@inheritdoc}
     */
    public function getCcRecipients(): array
    {
        return $this->vars->getCcRecipients()->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function getBccRecipients(): array
    {
        return $this->vars->getBccRecipients()->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function getCampaign(): ?UuidInterface
    {
        return $this->campaign;
    }

    /**
     * {@inheritdoc}
     */
    public function getReplyTo(): ?Address
    {
        return $this->vars->getReplyTo();
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplateName(): string
    {
        return $this->vars->getTemplateName();
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplateVars(): array
    {
        return $this->vars->getTemplateVars();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->vars->getCreatedAt();
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestPayload(): ?array
    {
        return $this->requestPayload;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(array $requestPayload): void
    {
        $this->requestPayload = $requestPayload;
    }

    /**
     * {@inheritdoc}
     */
    public function getResponsePayload(): ?array
    {
        return $this->responsePayload;
    }

    /**
     * {@inheritdoc}
     */
    public function deliver(array $responsePayload): void
    {
        $this->requestPayload = null;
        $this->responsePayload = $responsePayload;
        $this->deliveredAt = new \DateTimeImmutable();
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function getVars(): MailVars
    {
        return $this->vars;
    }
}

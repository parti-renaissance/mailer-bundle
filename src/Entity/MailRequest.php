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
 *     name="mail_requests",
 *     indexes={
 *         @ORM\Index(name="campaign_idx", columns={"campaign"})
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
     * @ORM\OneToMany(targetEntity="RecipientVars", cascade={"all"})
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
     * @param RecipientVars[] $recipientVars
     */
    public function __construct(MailVars $vars, array $recipientVars)
    {
        $this->vars = $vars;
        $this->recipientVars = new ArrayCollection($recipientVars);
        $this->campaign = $vars->getCampaign(); // duplication to perform concurrency check
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVars(): MailVars
    {
        return $this->vars;
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

    public function getRecipientsCount(): int
    {
        return $this->recipientVars->count();
    }

    /**
     * {@inheritdoc}
     *
     * @return Address[]|iterable
     */
    public function getCcRecipients(): iterable
    {
        return $this->vars->getCcRecipients();
    }

    /**
     * {@inheritdoc}
     *
     * @return Address[]|iterable
     */
    public function getBccRecipients(): iterable
    {
        return $this->vars->getBccRecipients();
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
    public function setRequestPayload(array $requestPayload): void
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
    public function setResponsePayload(array $responsePayload): void
    {
        $this->responsePayload = $responsePayload;
    }
}

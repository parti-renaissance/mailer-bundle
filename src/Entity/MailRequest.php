<?php

namespace EnMarche\MailerBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity
 * @ORM\Table(
 *     name="mail_requests",
 *     indexes={
 *         @ORM\Index(name="campaign_idx", columns={"campaign"})
 *     }
 * )
 */
class MailRequest
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
     * @return RecipientVars[]|Collection
     */
    public function getRecipientVars(): Collection
    {
        return $this->recipientVars;
    }

    public function getCampaign(): ?UuidInterface
    {
        return $this-$this->campaign;
    }
}

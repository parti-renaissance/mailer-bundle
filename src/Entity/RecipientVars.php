<?php

namespace EnMarche\MailerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="recipient_vars")
 */
class RecipientVars
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
     * @var Address
     *
     * @ORM\ManyToOne(targetEntity="Address", cascade={"persist", "refresh"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $address;

    /**
     * @var array
     *
     * @ORM\Column(type="json_array")
     */
    private $templateVars;

    /**
     * @var MailRequest
     *
     * @ORM\ManyToOne(targetEntity="MailRequest", inversedBy="recipientVars")
     */
    private $mailRequest;

    /**
     * @param string[] $templateVars
     */
    public function __construct(Address $address, array $templateVars)
    {
        $this->address = $address;
        $this->templateVars = $templateVars;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function getTemplateVars(): array
    {
        return $this->templateVars;
    }

    public function setMailRequest(MailRequest $mailRequest): void
    {
        if ($this->mailRequest) {
            throw new \LogicException('The recipient is already bound to a mail request id: '.($this->mailRequest->getId() ?: '"null"'));
        }

        $this->mailRequest = $mailRequest;
    }
}

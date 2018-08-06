<?php

namespace EnMarche\MailerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EnMarche\MailerBundle\Mail\MailInterface;

/**
 * A mail that holds a query for a lot of recipients.
 *
 * Also keeps an offset of the current addressed status.
 *
 * @ORM\Entity
 * @ORM\Table(name="lazy_mails")
 */
class LazyMail
{
    public const DEFAULT_BATCH_SIZE = 300;

    /**
     * @var int|null
     *
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned": true})
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string The serialized MailInterface
     *
     * @ORM\Column(type="text")
     */
    private $mail;

    /**
     * @var string The DQL query to get the model recipients
     *
     * @ORM\Column(type="text")
     */
    private $recipientsQuery;

    /**
     * @var string A PHP callable to get a RecipientInterface from model result
     *             Typically it will be OriginalSerializedClassName::createRecipientFor
     *
     * @ORM\Column
     */
    private $recipientFactory;

    /**
     * @var \DateTimeImmutable
     *
     * @ORM\Column(type="datetime_immutable", options={"default"="CURRENT_TIMESTAMP"})
     */
    private $preparedAt;

    /**
     * @var \DateTimeImmutable|null
     *
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $addressedAt = false;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", options={"default": 0})
     */
    private $currentOffset = 0;

    public function __construct(MailInterface $mail, string $recipientsQuery, string $recipientFactory)
    {
        if ($mail->hasToRecipients()) {
            throw new \InvalidArgumentException(\sprintf('The mail "%s" is already initialized: %s.', \get_class($mail), $mail->serialize()));
        }
        if (!\is_callable($recipientFactory)) {
            throw new \InvalidArgumentException(\sprintf('The recipient factory "%s" is not callable.'));
        }

        $this->mail = $mail->serialize();
        $this->recipientsQuery = $recipientsQuery;
        $this->recipientFactory = $recipientFactory;
        $this->preparedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMail(): string
    {
        return $this->mail;
    }

    public function getRecipientsQuery(): string
    {
        return $this->recipientsQuery;
    }

    public function getRecipientFactory(): string
    {
        return $this->recipientFactory;
    }

    public function getCurrentOffset(): int
    {
        return $this->currentOffset;
    }

    public function isAddressed(): bool
    {
        return $this->addressedAt instanceof \DateTimeImmutable;
    }

    public function schedule(): void
    {
        $this->currentOffset++;
    }

    public function addressed(): void
    {
        $this->addressedAt = new \DateTimeImmutable();
    }

    public function load(iterable $recipients): MailInterface
    {
        /** @var MailInterface $mail An uninitialized template */
        $mail = \unserialize($this->mail);
        $mail->init($recipients, $this->recipientFactory);

        return $mail;
    }
}

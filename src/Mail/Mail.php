<?php

namespace EnMarche\MailerBundle\Mail;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class Mail implements MailInterface
{
    public const TYPE_CAMPAIGN = 'campaign';
    public const TYPE_TRANSACTIONAL = 'transactional';

    public const DEFAULT_CHUNK_SIZE = 50;

    protected $toRecipients;
    protected $ccRecipients;
    protected $bccRecipients;
    protected $replyTo;
    protected $templateVars;
    protected $templateName;
    protected $type;
    protected $chunkId;

    /**
     * @param RecipientInterface[]|iterable $toRecipients
     * @param RecipientInterface|null $replyTo
     * @param RecipientInterface[]    $ccRecipients
     * @param RecipientInterface[]    $bccRecipients
     * @param string[]                $templateVars
     */
    final protected function __construct(
        iterable $toRecipients,
        RecipientInterface $replyTo = null,
        array $ccRecipients = [],
        array $bccRecipients = [],
        array $templateVars = []
    ) {
        $this->toRecipients = $toRecipients;
        $this->replyTo = $replyTo;
        $this->ccRecipients = $ccRecipients;
        $this->bccRecipients = $bccRecipients;
        $this->templateVars = $templateVars;
    }

    /**
     * {@inheritdoc}
     */
    final public function serialize()
    {
        // ensure the template key is resolved
        $this->getTemplateName();

        return \preg_replace('/C:\d+:[a-zA-Z0-9_]+Mail:/', 'C:4:Mail:', \serialize(\get_object_vars($this)));
    }

    /**
     * {@inheritdoc}
     */
    final public function unserialize($serialized)
    {
        foreach (\unserialize($serialized) as $property => $value) {
            $this->$property = $value;
        }
    }

    /**
     * {@inheritdoc}
     */
    final public function getToRecipients(): array
    {
        return $this->toRecipients;
    }

    /**
     * {@inheritdoc}
     */
    final public function getCcRecipients(): array
    {
        return $this->ccRecipients;
    }

    /**
     * {@inheritdoc}
     */
    final public function getBccRecipients(): array
    {
        return $this->bccRecipients;
    }

    /**
     * {@inheritdoc}
     */
    final public function getReplyTo(): ?RecipientInterface
    {
        return $this->replyTo;
    }

    /**
     * {@inheritdoc}
     */
    final public function getTemplateVars(): array
    {
        return $this->templateVars;
    }

    /**
     * Converts short class name to snake case.
     */
    final public function getTemplateName(): string
    {
        if ($this->templateName) {
            return $this->templateName;
        }

        $parts = \explode('\\', static::class);

        return $this->templateName = \preg_replace_callback('/(^|[a-z])([A-Z])/', function (array $matches) {
            return \strtolower(0 === \strlen($matches[1] ? $matches[2] : "{$matches[1]}_{$matches[2]}"));
        }, \preg_replace('/Mail$/', '', \end($parts)));
    }

    final public function getType(): string
    {
        return $this->type;
    }

    public function getChunkId(): ?UuidInterface
    {
        return $this->chunkId;
    }

    /**
     * {@inheritdoc}
     */
    final public function chunk(int $size = self::DEFAULT_CHUNK_SIZE): iterable
    {
        $chunk = 0;
        $recipients = [];

        foreach ($this->toRecipients as $recipient) {
            $recipients[] = $recipient;
            $chunk++;

            if (0 === $chunk % $size) {
                yield $this->createChunk($recipients);

                $recipients = [];
            }
        }

        if ($recipients) {
            yield $this->createChunk($recipients);
        }
    }

    /**
     * @param RecipientInterface[] $recipients
     */
    private function createChunk(array $recipients): MailInterface
    {
        if (!$this->chunkId) {
            $this->chunkId = Uuid::uuid4();
            $this->getTemplateName(); // ensure the template name is resolved for perf
        }

        foreach ($recipients as $recipient) {
            $recipient->setChunkId($this->chunkId);
        }

        $chunk = clone $this;
        $chunk->toRecipients = $recipients;

        return $chunk;
    }
}

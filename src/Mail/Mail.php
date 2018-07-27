<?php

namespace EnMarche\MailerBundle\Mail;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class Mail implements MailInterface
{
    public const TYPE_CAMPAIGN = 'campaign';
    public const TYPE_TRANSACTIONAL = 'transactional';

    public const DEFAULT_CHUNK_SIZE = 50;

    protected $type;

    private $app;
    private $toRecipients;
    private $ccRecipients;
    private $bccRecipients;
    private $replyTo;
    private $templateVars;
    private $templateName;
    private $chunkId;

    /**
     * @param RecipientInterface[]|iterable $toRecipients
     * @param RecipientInterface|null       $replyTo
     * @param RecipientInterface[]          $ccRecipients
     * @param RecipientInterface[]          $bccRecipients
     * @param string[]                      $templateVars
     */
    final protected function __construct(
        string $app,
        iterable $toRecipients,
        RecipientInterface $replyTo = null,
        array $ccRecipients = [],
        array $bccRecipients = [],
        array $templateVars = []
    ) {
        $this->app = $app;
        $this->toRecipients = $toRecipients;
        $this->replyTo = $replyTo;
        $this->ccRecipients = $ccRecipients;
        $this->bccRecipients = $bccRecipients;
        $this->templateVars = $templateVars;
    }

    public function getApp(): string
    {
        return $this->app;
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
    final public function hasCopyRecipients(): bool
    {
        return $this->ccRecipients || $this->bccRecipients;
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
     * {@inheritdoc}
     *
     * Converts short class name to snake case by default.
     */
    public function getTemplateName(): string
    {
        if ($this->templateName) {
            return $this->templateName;
        }

        $parts = \explode('\\', static::class);

        return $this->templateName = \preg_replace_callback('/(^|[a-z])([A-Z])/', function (array $matches) {
            return \strtolower(0 === \strlen($matches[1] ? $matches[2] : "{$matches[1]}_{$matches[2]}"));
        }, \preg_replace('/Mail$/', '', \end($parts)));
    }

    /**
     * {@inheritdoc}
     */
    final public function getType(): string
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    final public function getChunkId(): ?UuidInterface
    {
        return $this->chunkId;
    }

    /**
     * {@inheritdoc}
     */
    public function chunk(int $size = self::DEFAULT_CHUNK_SIZE): iterable
    {
        foreach (\array_chunk($this->toRecipients, $size) as $recipients) {
            yield $this->createChunk($recipients);
        }
    }

    /**
     * {@inheritdoc}
     */
    final public function serialize(): string
    {
        // Enforce serializing the Mail class instead of the children one to prevent unserializing an unknow class later
        $mail = new self(
            $this->app,
            $this->toRecipients,
            $this->replyTo,
            $this->ccRecipients,
            $this->bccRecipients,
            $this->templateVars
        );
        // ensure the template key is resolved
        $mail->templateName = $this->templateName ?: $this->getTemplateName();
        $mail->chunkId = $this->chunkId;

        return \serialize($mail);
    }

    /**
     * @param RecipientInterface[] $recipients
     */
    private function createChunk(array $recipients): self
    {
        if (!$this->chunkId) {
            $this->chunkId = Uuid::uuid4();
            $this->getTemplateName(); // ensure the template name is resolved for perf
        }

        $chunk = clone $this;
        $chunk->toRecipients = $recipients;

        return $chunk;
    }
}

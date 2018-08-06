<?php

namespace EnMarche\MailerBundle\Mail;

use EnMarche\MailerBundle\Exception\InvalidMailException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class Mail implements MailInterface
{
    public const TYPE_CAMPAIGN = 'campaign';
    public const TYPE_TRANSACTIONAL = 'transactional';
    public const TYPES = [
        self::TYPE_CAMPAIGN,
        self::TYPE_TRANSACTIONAL,
    ];

    public const DEFAULT_CHUNK_SIZE = 50;

    protected $type;

    private $app;
    private $toRecipients;
    private $ccRecipients;
    private $bccRecipients;
    private $replyTo;
    private $templateVars;
    private $templateName;
    private $createdAt;
    private $chunkId;

    /**
     * @param RecipientInterface[]|null $toRecipients
     * @param RecipientInterface|null   $replyTo
     * @param RecipientInterface[]      $ccRecipients
     * @param RecipientInterface[]      $bccRecipients
     * @param string[]                  $templateVars
     */
    final protected function __construct(
        string $app,
        ?iterable $toRecipients,
        RecipientInterface $replyTo = null,
        array $ccRecipients = [],
        array $bccRecipients = [],
        array $templateVars = []
    ) {
        $this->app = $app;
        $this->replyTo = $replyTo;
        $this->ccRecipients = $ccRecipients;
        $this->bccRecipients = $bccRecipients;
        $this->templateVars = MailUtils::validateTemplateVars($templateVars);
        $this->createdAt = new \DateTimeImmutable();

        // Allow lazy loading recipients
        if (null !== $toRecipients) {
            $this->init($toRecipients);
        }
    }

    /**
     * {@inheritdoc}
     */
    final public function init(iterable $recipients, callable $recipientFactory = null): void
    {
        if (null !== $this->toRecipients) {
            throw new \BadMethodCallException('Mail is already initialized.');
        }

        foreach ($recipients as $recipient) {
            if (!$recipient instanceof RecipientInterface && !$recipientFactory) {
                throw new \RuntimeException(\sprintf('The recipient must either implement "%s" or a factory must be passed as second argument of %s. Got "%s".', RecipientInterface::class, __METHOD__, \is_object($recipient) ? get_class($recipient) : gettype($recipient)));
            }
            if (!$recipient instanceof RecipientInterface) {
                $recipient = $recipientFactory($recipient);
                if (!$recipient instanceof RecipientInterface) {
                    throw new \InvalidArgumentException(\sprintf('Expected an instance of "%s" but got "%s".', RecipientInterface::class, \is_object($recipient) ? get_class($recipient) : gettype($recipient)));
                }
            }
            $to[$recipient->getEmail()] = $recipient;
        }

        if (empty($to)) {
            throw new InvalidMailException('No recipients.');
        }

        $this->toRecipients = $to;
    }

    final public function getApp(): string
    {
        return $this->app;
    }

    /**
     * {@inheritdoc}
     */
    final public function getToRecipients(): array
    {
        return $this->toRecipients ?: [];
    }

    /**
     * {@inheritdoc}
     */
    final public function hasToRecipients(): bool
    {
        return null !== $this->toRecipients;
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

        return $this->templateName = \preg_replace_callback(
            '/(^|[a-z])([A-Z]+)([a-z])?/',
            function (array $matches) {
                if (\strlen($matches[2]) > 1 && isset($matches[3]) && \strlen($matches[3]) > 0) {
                    // we need to keep the last cap for the following part, i.e: xxxHTMLFormXxx => xxx_html_form_xxx
                    $lowerCaps = \substr($matches[2], 0, -1);

                    return \strtolower(\sprintf(
                        '%s_%s',
                        0 === \strlen($matches[1]) ? $lowerCaps : "{$matches[1]}_$lowerCaps",
                        \substr($matches[2], -1).$matches[3]
                    ));
                }

                return \strtolower(
                    (0 === \strlen($matches[1]) ? $matches[2] : "{$matches[1]}_{$matches[2]}").($matches[3] ?? '')
                );
            },
            \preg_replace('/Mail$/', '', \end($parts))
        );
    }

    /**
     * {@inheritdoc}
     */
    final public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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
        // Enforce serializing self instead of the children class to prevent unserializing an unknown class later
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
        $mail->createdAt = $this->createdAt;

        if (!$this->toRecipients) {
            // mail is a lazy template, let's sign it before batching
            $mail->sign();
        }

        return \serialize($mail);
    }

    /**
     * @param RecipientInterface[] $recipients
     */
    private function createChunk(array $recipients): self
    {
        if (!$this->chunkId) {
            $this->sign();
            $this->getTemplateName(); // ensure the template name is resolved for perf
        }

        $chunk = clone $this;
        $chunk->toRecipients = $recipients;

        return $chunk;
    }

    /**
     * Makes the mail unique before batching or chunking.
     */
    private function sign(): void
    {
        $this->chunkId = Uuid::uuid4();
    }

    private function __clone()
    {
    }
}

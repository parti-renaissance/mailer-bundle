<?php

namespace EnMarche\MailerBundle\Mailer\Transporter;

use EnMarche\MailerBundle\Mail\ChunkableMailInterface;
use EnMarche\MailerBundle\Mail\Mail;
use EnMarche\MailerBundle\Mail\MailInterface;
use EnMarche\MailerBundle\Mailer\TransporterInterface;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class AmqpMailTransporter implements TransporterInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private $producer;
    private $chunkSize;
    private $routingKeyPrefix;

    public function __construct(ProducerInterface $producer, int $chunkSize = Mail::DEFAULT_CHUNK_SIZE, string $routingKeyPrefix = 'em_mails')
    {
        $this->producer = $producer;
        $this->chunkSize = $chunkSize;
        $this->routingKeyPrefix = $routingKeyPrefix;
        $this->logger = new NullLogger();
    }

    public function transport(MailInterface $mail): void
    {
        $template = $mail->getTemplateName();
        $routingKey = $this->getRoutingKey($mail);

        if ($mail instanceof ChunkableMailInterface) {
            $chunkId = null;

            foreach ($mail->chunk($this->chunkSize) as $chunk) {
                if (!$chunkId) {
                    $chunkId = $mail->getChunkId()->toString();
                }
                $this->log($template, $routingKey, $chunk->getToRecipients(), $chunkId);
                $this->publish($chunk, $routingKey);
            }
        } else {
            $this->log($template, $routingKey, $mail->getToRecipients());
            $this->publish($mail, $routingKey);
        }
    }

    private function publish(MailInterface $mail, string $routingKey): void
    {
        $this->producer->publish($mail->serialize(), $routingKey);
    }

    private function getRoutingKey(MailInterface $mail): string
    {
        return implode('.', [
            $this->routingKeyPrefix,
            $mail->getType(),
            $mail->getApp(),
        ]);
    }

    private function log(string $template, string $routingKey, array $to, string $chunkId = null): void
    {
        if (!$this->logger) {
            return;
        }
        if ($chunkId) {
            $this->logger->info(\sprintf(
                'Publishing mail chunk "%s(%s)" on "%s" with %d recipient%s.',
                $template,
                $chunkId,
                $routingKey,
                $toCount = \count($to),
                $toCount > 1 ? 's' : ''
            ));
        } else {
            $this->logger->info(\sprintf(
                'Publishing mail "%s" on "%s" with %d recipient%s.',
                $template,
                $routingKey,
                $toCount = \count($to),
                $toCount > 1 ? 's' : ''
            ));
        }
    }
}

<?php

namespace EnMarche\MailerBundle\Mailer\Transporter;

use EnMarche\MailerBundle\Mail\ChunkableMailInterface;
use EnMarche\MailerBundle\Mail\Mail;
use EnMarche\MailerBundle\Mail\MailInterface;
use EnMarche\MailerBundle\Mailer\TransporterInterface;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use Psr\Log\LoggerInterface;

class AmqpMailTransporter implements TransporterInterface
{
    private $producer;
    private $chunkSize;
    private $routingKey;
    private $logger;

    public function __construct(ProducerInterface $producer, int $chunkSize = Mail::DEFAULT_CHUNK_SIZE, string $routingKey = 'mailer.scheduled_mail', LoggerInterface $logger = null)
    {
        $this->producer = $producer;
        $this->chunkSize = $chunkSize;
        $this->routingKey = $routingKey;
        $this->logger = $logger;
    }

    public function transport(MailInterface $mail): void
    {
        $template = $mail->getTemplateName();
        $routingKey = $this->getRoutingKey($mail);

        if ($mail instanceof ChunkableMailInterface) {
            $chunkId = $mail->getChunkId()->toString();

            foreach ($mail->chunk($this->chunkSize) as $chunk) {
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
        return $this->routingKey.'_'.$mail->getType();
    }

    private function log(string $template, string $routingKey, array $to, string $chunkId = null): void
    {
        if (!$this->logger) {
            return;
        }
        if ($chunkId) {
            $this->logger->info(\sprintf(
                'Publishing mail chunk "%s(%s)" on "%s" with %d recipients.',
                $template,
                $chunkId,
                $routingKey,
                \count($to)
            ));
        } else {
            $this->logger->info(\sprintf(
                'Publishing mail "%s" on "%s" with %d recipients.',
                $template,
                $routingKey,
                \count($to)
            ));
        }
    }
}

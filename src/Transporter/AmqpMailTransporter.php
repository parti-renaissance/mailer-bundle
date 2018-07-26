<?php

namespace EnMarche\MailerBundle\Transporter;

use EnMarche\MailerBundle\Mail\ChunkableMailInterface;
use EnMarche\MailerBundle\Mail\Mail;
use EnMarche\MailerBundle\Mail\MailInterface;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;

class AmqpMailTransporter implements TransporterInterface
{
    private $producer;
    private $chunkSize;
    private $routingKey;

    public function __construct(ProducerInterface $producer, int $chunkSize = Mail::DEFAULT_CHUNK_SIZE, string $routingKey = 'mailer.scheduled_mail')
    {
        $this->producer = $producer;
        $this->chunkSize = $chunkSize;
        $this->routingKey = $routingKey;
    }

    public function schedule(MailInterface $mail): void
    {
        if ($mail instanceof ChunkableMailInterface) {
            foreach ($mail->chunk($this->chunkSize) as $chunk) {
                $this->publish($chunk);
            }
        } else {
            $this->publish($mail);
        }
    }

    private function publish(MailInterface $mail): void
    {
        $this->producer->publish($mail->serialize(), $this->routingKey.'_'.$mail->getType());
    }
}

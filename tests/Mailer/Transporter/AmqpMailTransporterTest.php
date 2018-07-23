<?php

namespace EnMarche\MailerBundle\Tests\Mailer\Transporter;

use EnMarche\MailerBundle\Mail\CampaignMail;
use EnMarche\MailerBundle\Mail\Mail;
use EnMarche\MailerBundle\Mail\MailBuilder;
use EnMarche\MailerBundle\Mail\MailInterface;
use EnMarche\MailerBundle\Mail\Recipient;
use EnMarche\MailerBundle\Mail\TransactionalMail;
use EnMarche\MailerBundle\Mailer\Transporter\AmqpMailTransporter;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AmqpMailTransporterTest extends TestCase
{
    private const ROUTING_KEY = 'mails_test';

    /**
     * @var MockObject|ProducerInterface
     */
    private $producer;

    /**
     * @var MockObject|LoggerInterface
     */
    private $logger;

    /**
     * @var AmqpMailTransporter
     */
    private $transporter;

    protected function setUp()
    {
        $this->producer = $this->createMock(ProducerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->transporter = new AmqpMailTransporter(
            $this->producer,
            Mail::DEFAULT_CHUNK_SIZE,
            self::ROUTING_KEY,
            $this->logger
        );
    }

    protected function tearDown()
    {
        $this->producer = null;
        $this->logger = null;
        $this->transporter = null;
    }

    public function testTransport()
    {
        $to = ['email'];
        $mail = $this->getMail([$to]);
        $routingKey = self::ROUTING_KEY.'_'.$mail->getType().'_'.$mail->getApp();

        $this->logger->expects($this->once())
            ->method('info')
            ->with(\sprintf('Publishing mail "%s" on "%s" with %d recipient.', $mail->getTemplateName(), $routingKey, 1))
        ;
        $this->producer->expects($this->once())
            ->method('publish')
            ->with($mail->serialize(), $routingKey)
        ;

        $this->transporter->transport($mail);
    }

    public function testTransportChunkable()
    {
        $to = [];
        for ($i = 0; $i < Mail::DEFAULT_CHUNK_SIZE + 1; $i++) {
            $to[] = ["email_$i"];
        }
        $mail = $this->getMail($to, true);
        $chunks = $mail->chunk();
        foreach ($chunks as $chunk) {
            break; // call once to generate the id
        }

        $routingKey = self::ROUTING_KEY.'_'.$mail->getType().'_'.$mail->getApp();
        $logMessage = \sprintf(
            'Publishing mail chunk "%s(%s)" on "%s" with %s recipients.',
            $mail->getTemplateName(),
            $mail->getChunkId()->toString(),
            $routingKey,
            '%d'
        );

        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                [\sprintf($logMessage, Mail::DEFAULT_CHUNK_SIZE)],
                [\sprintf(\substr($logMessage, 0, -2).'.', 1)]
            )
        ;

        $this->producer->expects($this->exactly(2))
            ->method('publish')
        ;

        $this->transporter->transport($mail);
    }

    private function getMail(array $to, bool $chunkable = false): MailInterface
    {
        return MailBuilder::create($chunkable ? CampaignMail::class : TransactionalMail::class, 'test')
            ->setToRecipients(\array_map(function(array $to) { return new Recipient(...$to); }, $to))
            ->getMail()
       ;
    }
}

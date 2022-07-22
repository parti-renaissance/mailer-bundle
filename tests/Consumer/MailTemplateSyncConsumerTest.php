<?php

namespace EnMarche\MailerBundle\Tests\Consumer;

use Doctrine\Common\Persistence\ObjectManager;
use EnMarche\MailerBundle\Consumer\MailTemplateSyncConsumer;
use EnMarche\MailerBundle\Repository\TemplateRepository;
use EnMarche\MailerBundle\Template\Synchronization\SynchronizerRegistryInterface;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MailTemplateSyncConsumerTest extends TestCase
{
    /** @var MailTemplateSyncConsumer */
    private $consumer;
    private $managerMock;
    private $templateRepositoryMock;
    private $synchronizerRegistryMock;

    /** @var LoggerInterface|MockObject */
    private $loggerMock;

    protected function setUp()
    {
        $this->managerMock = $this->createMock(ObjectManager::class);
        $this->templateRepositoryMock = $this->createMock(TemplateRepository::class);
        $this->synchronizerRegistryMock = $this->createMock(SynchronizerRegistryInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->consumer = new MailTemplateSyncConsumer(
            $this->managerMock,
            $this->templateRepositoryMock,
            $this->synchronizerRegistryMock
        );

        $this->consumer->setLogger($this->loggerMock);
    }

    /**
     * @dataProvider getInvalidMessage
     */
    public function testInvalidMessageLogMessage(string $message): void
    {
        $this->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with('Invalid message.', ['message' => $message])
        ;

        $this->consumer->execute($this->getAMQPMessage($message));
    }

    public function getInvalidMessage(): iterable
    {
        yield 'Hey!';
        yield '["Plop", "Banan"]';
    }

    private function getAMQPMessage(string $msg): AMQPMessage
    {
        return new AMQPMessage($msg);
    }
}

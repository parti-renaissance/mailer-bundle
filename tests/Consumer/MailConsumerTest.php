<?php

namespace EnMarche\MailerBundle\Tests\Consumer;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use EnMarche\MailerBundle\Client\MailRequestFactoryInterface;
use EnMarche\MailerBundle\Client\MailRequestInterface;
use EnMarche\MailerBundle\Consumer\MailConsumer;
use EnMarche\MailerBundle\Tests\LoggerTestTrait;
use EnMarche\MailerBundle\Tests\Test\DummyMail;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MailConsumerTest extends TestCase
{
    use LoggerTestTrait;

    private const ROUTING_KEY = 'em_mail_requests';

    /**
     * @var MockObject|ProducerInterface
     */
    private $producer;

    /**
     * @var MockObject|EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var MockObject|MailRequestFactoryInterface
     */
    private $mailRequestFactory;

    /**
     * @var MailConsumer
     */
    private $mailConsumer;

    protected function setUp()
    {
        $this->producer = $this->createMock(ProducerInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->mailRequestFactory = $this->createMock(MailRequestFactoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->mailConsumer = new MailConsumer(
            $this->producer,
            self::ROUTING_KEY,
            $this->entityManager,
            $this->mailRequestFactory,
            $this->logger
        );
    }

    protected function tearDown()
    {
        $this->producer = null;
        $this->entityManager = null;
        $this->mailRequestFactory = null;
        $this->logger = null;
        $this->mailConsumer = null;
    }

    public function testExecute()
    {
        $this->expectsLog('warning', null);
        $this->expectsLog('error', null);

        $mail = new DummyMail();
        $mailRequest = $this->createMock(MailRequestInterface::class);

        $this->mailRequestFactory->expects($this->once())
            ->method('createRequestForMail')
            ->with($mail)
            ->willReturn($mailRequest)
        ;

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($mailRequest)
        ;
        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $requestId = 1;

        $mailRequest->expects($this->once())
            ->method('getId')
            ->willReturn($requestId)
        ;
        $mailRequest->expects($this->once())
            ->method('getType')
            ->willReturn('fake')
        ;
        $mailRequest->expects($this->once())
            ->method('getApp')
            ->willReturn('test')
        ;

        $this->producer->expects($this->once())
            ->method('publish')
            ->with($requestId, self::ROUTING_KEY.'_'.$mail->getType().'_'.$mail->getApp())
        ;

        $this->assertSame(
            ConsumerInterface::MSG_ACK,
            $this->mailConsumer->execute($this->getAMQPMessage($mail))
        );
    }

    public function testExecuteInvalidMail()
    {
        $this->mailRequestFactory->expects($this->never())
            ->method('createRequestForMail')
        ;

        $this->entityManager->expects($this->never())
            ->method('persist')
        ;
        $this->entityManager->expects($this->never())
            ->method('flush')
        ;

        $this->producer->expects($this->never())
            ->method('publish')
        ;

        $mail = new \stdClass();

        $this->expectsLog('warning', null);
        $this->expectsLog(
            'error',
            'Invalid unserialized message. Expected an implementation of "EnMarche\MailerBundle\Mail\MailInterface".',
            ['message' => \serialize($mail)]
        );

        $this->assertSame(
            ConsumerInterface::MSG_REJECT,
            $this->mailConsumer->execute($this->getAMQPMessage($mail))
        );
    }

    public function testExecuteDuplicateMailRequest()
    {
        $mail = new DummyMail();
        $exception = $this->createMock(UniqueConstraintViolationException::class);

        $this->expectsLog('error', null);
        $this->expectsLog('warning', 'The mail could not be processed. Retrying later.', [
            'mail' => $mail,
            'exception' => $exception,
        ]);

        $this->mailRequestFactory->expects($this->once())
            ->method('createRequestForMail')
            ->with($mail)
            ->willThrowException($exception)
        ;

        $this->entityManager->expects($this->never())
            ->method('persist')
        ;
        $this->entityManager->expects($this->never())
            ->method('flush')
        ;

        $this->producer->expects($this->never())
            ->method('publish')
        ;

        $this->assertSame(
            ConsumerInterface::MSG_REJECT_REQUEUE,
            $this->mailConsumer->execute($this->getAMQPMessage($mail))
        );
    }

    public function testExecuteUnexpected()
    {
        $mail = new DummyMail();
        $exception = new \Exception('exception_message');

        $this->expectsLog('error', 'Something went wrong: exception_message', [
            'mail' => $mail,
            'exception' => $exception,
        ]);
        $this->expectsLog('warning', null);

        $this->mailRequestFactory->expects($this->once())
            ->method('createRequestForMail')
            ->with($mail)
            ->willThrowException($exception)
        ;

        $this->entityManager->expects($this->never())
            ->method('persist')
        ;
        $this->entityManager->expects($this->never())
            ->method('flush')
        ;

        $this->producer->expects($this->never())
            ->method('publish')
        ;

        $this->assertSame(
            ConsumerInterface::MSG_REJECT,
            $this->mailConsumer->execute($this->getAMQPMessage($mail))
        );
    }

    private function getAMQPMessage($msg): AMQPMessage
    {
        return new AMQPMessage(\serialize($msg));
    }
}

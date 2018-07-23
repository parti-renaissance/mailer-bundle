<?php

namespace EnMarche\MailerBundle\Tests\Consumer;

use Doctrine\ORM\EntityManagerInterface;
use EnMarche\MailerBundle\Client\MailClientInterface;
use EnMarche\MailerBundle\Client\MailClientsRegistryInterface;
use EnMarche\MailerBundle\Client\MailRequestInterface;
use EnMarche\MailerBundle\Consumer\MailRequestConsumer;
use EnMarche\MailerBundle\Exception\InvalidMailRequestException;
use EnMarche\MailerBundle\Exception\InvalidMailResponseException;
use EnMarche\MailerBundle\Repository\MailRequestRepository;
use EnMarche\MailerBundle\Tests\LoggerTestTrait;
use EnMarche\MailerBundle\Test\DummyMailRequest;
use GuzzleHttp\Exception\SeekException;
use GuzzleHttp\Exception\TransferException;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class MailRequestConsumerTest extends TestCase
{
    use LoggerTestTrait;

    /**
     * @var MockObject|MailRequestRepository
     */
    private $mailRequestRepository;

    /**
     * @var MockObject|EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var MockObject|MailClientsRegistryInterface
     */
    private $mailClientRegistry;

    /**
     * @var MailRequestConsumer
     */
    private $mailRequestConsumer;

    protected function setUp()
    {
        $this->mailRequestRepository = $this->createMock(MailRequestRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->mailClientRegistry = $this->createMock(MailClientsRegistryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->mailRequestConsumer = new MailRequestConsumer(
            $this->mailRequestRepository,
            $this->entityManager,
            $this->mailClientRegistry,
            $this->logger
        );
    }

    protected function tearDown()
    {
        $this->mailRequestRepository = null;
        $this->entityManager = null;
        $this->mailClientRegistry = null;
        $this->logger = null;
        $this->mailRequestConsumer = null;
    }

    public function testExecute()
    {
        $this->expectsLog('warning', null);
        $this->expectsLog('error', null);

        $message = '1';
        $mailRequest = new DummyMailRequest();

        $this->mailRequestRepository->expects($this->once())
            ->method('find')
            ->with($message)
            ->willReturn($mailRequest)
        ;

        $mailClient = $this->createMock(MailClientInterface::class);

        $this->mailClientRegistry->expects($this->once())
            ->method('getClientForMailRequest')
            ->with($mailRequest)
            ->willReturn($mailClient)
        ;

        $mailClient->expects($this->once())
            ->method('send')
            ->with($mailRequest)
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->assertSame(
            ConsumerInterface::MSG_ACK,
            $this->mailRequestConsumer->execute($this->getAMQPMessage($message))
        );
    }

    public function provideInvalidMessage()
    {
        yield 0 => [0];
        yield 'negative' => [-1];
        yield 'alpha' => ['a'];
        yield 'array' => [[]];
        yield MailRequestInterface::class => [new DummyMailRequest()];
    }

    /**
     * @dataProvider provideInvalidMessage
     */
    public function testExecuteInvalidMessage($msg)
    {
        $this->mailRequestRepository->expects($this->never())
            ->method('find')
        ;

        $this->mailClientRegistry->expects($this->never())
            ->method('getClientForMailRequest')
        ;

        $this->entityManager->expects($this->never())
            ->method('flush')
        ;

        $this->expectsLog('warning', null);
        $this->expectsLog(
            'error',
            'Invalid message. Expected positive integer.',
            ['message' => $msg]
        );

        $this->assertSame(
            ConsumerInterface::MSG_REJECT,
            $this->mailRequestConsumer->execute($this->getAMQPMessage($msg))
        );
    }

    public function testExecuteNotFoundMailRequest()
    {
        $msg = 1;

        $this->mailRequestRepository->expects($this->once())
            ->method('find')
            ->with($msg)
            ->willReturn(null)
        ;

        $this->mailClientRegistry->expects($this->never())
            ->method('getClientForMailRequest')
        ;

        $this->entityManager->expects($this->never())
            ->method('flush')
        ;

        $this->expectsLog('warning', null);
        $this->expectsLog('error', 'Invalid message. Mail request id 1 not found.');

        $this->assertSame(
            ConsumerInterface::MSG_REJECT,
            $this->mailRequestConsumer->execute($this->getAMQPMessage($msg))
        );
    }

    public function testExecuteAlreadySentMailRequest()
    {
        $msg = 1;
        $mailRequest = new DummyMailRequest();
        $mailRequest->deliver(['sent']);

        $this->mailRequestRepository->expects($this->once())
            ->method('find')
            ->with($msg)
            ->willReturn($mailRequest)
        ;

        $this->mailClientRegistry->expects($this->never())
            ->method('getClientForMailRequest')
        ;

        $this->entityManager->expects($this->never())
            ->method('flush')
        ;

        $this->expectsLog('warning', null);
        $this->expectsLog('error', 'Mail request already processed.', ['mail_request' => $mailRequest]);

        $this->assertSame(
            ConsumerInterface::MSG_REJECT,
            $this->mailRequestConsumer->execute($this->getAMQPMessage($msg))
        );
    }

    public function provideClientExceptionClass()
    {
        // Those two covers all GuzzleException cases by inheritance
        yield TransferException::class => [TransferException::class];
        yield SeekException::class => [SeekException::class];
    }

    /**
     * @dataProvider provideClientExceptionClass
     */
    public function testExecuteWhenClientFails($exceptionClass)
    {
        $msg = 1;
        $mailRequest = new DummyMailRequest();
        $clientException = $this->createMock($exceptionClass);

        $this->mailRequestRepository->expects($this->once())
            ->method('find')
            ->with($msg)
            ->willReturn($mailRequest)
        ;

        $mailClient = $this->createMock(MailClientInterface::class);

        $this->mailClientRegistry->expects($this->once())
            ->method('getClientForMailRequest')
            ->with($mailRequest)
            ->willThrowException($clientException)
        ;

        $this->entityManager->expects($this->never())
            ->method('flush')
        ;

        $this->expectsLog('error', null);
        $this->expectsLog('warning', 'The mail request could not be processed. Retrying later.', [
            'mail_request' => $mailRequest,
            'exception' => $clientException,
        ]);

        $this->assertSame(
            ConsumerInterface::MSG_REJECT_REQUEUE,
            $this->mailRequestConsumer->execute($this->getAMQPMessage($msg))
        );
    }

    public function testExecuteOnInvalidMailRequest()
    {
        $msg = 1;
        $mailRequest = new DummyMailRequest();
        $exceptionMessage = 'test';
        $requestException = new InvalidMailRequestException($exceptionMessage);

        $this->mailRequestRepository->expects($this->once())
            ->method('find')
            ->with($msg)
            ->willReturn($mailRequest)
        ;

        $this->mailClientRegistry->expects($this->once())
            ->method('getClientForMailRequest')
            ->with($mailRequest)
            ->willThrowException($requestException)
        ;

        $this->entityManager->expects($this->never())
            ->method('flush')
        ;

        $this->expectsLog('warning', null);
        $this->expectsLog('error', 'test', [
            'mail_request' => $mailRequest,
            'exception' => $requestException,
        ]);

        $this->assertSame(
            ConsumerInterface::MSG_REJECT,
            $this->mailRequestConsumer->execute($this->getAMQPMessage($msg))
        );
    }

    public function provideInvalidResponseCase()
    {
        yield 'Client error' => [400, ConsumerInterface::MSG_REJECT];
        yield 'Server error' => [500, ConsumerInterface::MSG_REJECT_REQUEUE];
    }

    /**
     * @dataProvider provideInvalidResponseCase
     */
    public function testExecuteOnInvalidMailResponse(int $statusCode, int $expectedResult)
    {
        $msg = 1;
        $mailRequest = $this->getSavedMailRequest();
        $invalidResponse = $this->createMock(ResponseInterface::class);
        $invalidResponse->expects($this->any())
            ->method('getBody')
            ->willReturn('test')
        ;
        $invalidResponse->expects($this->any())
            ->method('getStatusCode')
            ->willReturn($statusCode)
        ;
        $requestException = new InvalidMailResponseException($mailRequest, $invalidResponse);

        $this->mailRequestRepository->expects($this->once())
            ->method('find')
            ->with($msg)
            ->willReturn($mailRequest)
        ;

        $this->mailClientRegistry->expects($this->once())
            ->method('getClientForMailRequest')
            ->with($mailRequest)
            ->willThrowException($requestException)
        ;

        $this->entityManager->expects($this->never())
            ->method('flush')
        ;

        $this->expectsLog('warning', null);
        $this->expectsLog(
            'error',
            \sprintf("Invalid response (code: %d) for mail request (id: 1):\n\"test\"", $statusCode),
            [
                'mail_request' => $mailRequest,
                'exception' => $requestException,
            ]
        );

        $this->assertSame(
            $expectedResult,
            $this->mailRequestConsumer->execute($this->getAMQPMessage($msg))
        );
    }

    private function getAMQPMessage($msg): AMQPMessage
    {
        return new AMQPMessage($msg);
    }

    private function getSavedMailRequest()
    {
        return new class() extends DummyMailRequest
        {
            protected $id = 1;
        };
    }
}

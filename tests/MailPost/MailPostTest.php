<?php

namespace EnMarche\MailerBundle\Tests\Toto;

use EnMarche\MailerBundle\Mail\MailFactoryInterface;
use EnMarche\MailerBundle\Mail\Recipient;
use EnMarche\MailerBundle\Mail\RecipientInterface;
use EnMarche\MailerBundle\Mailer\MailerInterface;
use EnMarche\MailerBundle\Tests\Mail\TotoMail;
use EnMarche\MailerBundle\Tests\Test\DummyMail;
use EnMarche\MailerBundle\MailPost\MailPost;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MailPostTest extends TestCase
{
    /**
     * @var MockObject|MailerInterface
     */
    private $mailer;

    /**
     * @var MockObject|MailFactoryInterface
     */
    private $mailFactory;

    /**
     * @var MailPost
     */
    private $mailPost;

    protected function setUp()
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->mailFactory = $this->createMock(MailFactoryInterface::class);
        $this->mailPost = new MailPost($this->mailer, $this->mailFactory);
    }

    protected function tearDown()
    {
        $this->mailer = null;
        $this->mailFactory = null;
        $this->mailPost = null;
    }

    public function testAddressOneRecipient()
    {
        $mail = new DummyMail();
        $mailClass = TotoMail::class;
        $to = $this->createMock(RecipientInterface::class);
        $replyTo = new Recipient('');
        $templateVars = ['var' => 'test'];

        $this->mailFactory->expects($this->once())
            ->method('createForClass')
            ->with($mailClass, [$to], $replyTo, $templateVars)
            ->willReturn($mail)
        ;
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($mail)
        ;

        $this->mailPost->address($mailClass, $to, $replyTo, $templateVars);
    }

    public function testAddressManyRecipients()
    {
        $mail = new DummyMail();
        $mailClass = TotoMail::class;
        $to = [$this->createMock(RecipientInterface::class)];
        $replyTo = new Recipient('');
        $templateVars = ['var' => 'test'];

        $this->mailFactory->expects($this->once())
            ->method('createForClass')
            ->with($mailClass, $to, $replyTo, $templateVars)
            ->willReturn($mail)
        ;
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($mail)
        ;

        $this->mailPost->address($mailClass, $to, $replyTo, $templateVars);
    }
}

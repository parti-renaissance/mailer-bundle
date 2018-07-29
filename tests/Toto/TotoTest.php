<?php

namespace EnMarche\MailerBundle\Tests\Toto;

use EnMarche\MailerBundle\Mail\MailFactoryInterface;
use EnMarche\MailerBundle\Mail\Recipient;
use EnMarche\MailerBundle\Mailer\MailerInterface;
use EnMarche\MailerBundle\Tests\Mail\TotoMail;
use EnMarche\MailerBundle\Tests\Test\DummyMail;
use EnMarche\MailerBundle\Toto\Toto;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TotoTest extends TestCase
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
     * @var Toto
     */
    private $toto;

    protected function setUp()
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->mailFactory = $this->createMock(MailFactoryInterface::class);
        $this->toto = new Toto($this->mailer, $this->mailFactory);
    }

    protected function tearDown()
    {
        $this->mailer = null;
        $this->mailFactory = null;
        $this->toto = null;
    }

    public function testHeah()
    {
        $mail = new DummyMail();
        $mailClass = TotoMail::class;
        $to = [];
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

        $this->toto->heah($mailClass, $to, $replyTo, $templateVars);
    }
}

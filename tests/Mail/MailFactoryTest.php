<?php

namespace EnMarche\MailerBundle\Tests\Mail;

use EnMarche\MailerBundle\Mail\Mail;
use EnMarche\MailerBundle\Mail\MailFactory;
use EnMarche\MailerBundle\Mail\Recipient;
use EnMarche\MailerBundle\Mail\RecipientInterface;
use PHPUnit\Framework\TestCase;

class MailFactoryTest extends TestCase
{
    /**
     * @var MailFactory
     */
    private $mailFactory;

    protected function setUp()
    {
        $this->mailFactory = new MailFactory('test');
    }

    protected function tearDown()
    {
        $this->mailFactory = null;
    }

    public function provideMailData()
    {
        yield [
            TotoMail::class,
            [$this->getRecipient('email')],
            null, // no reply to
            [], // no template vars
        ];
        yield [
            HeahMail::class,
            [$this->getRecipient('email')],
            $this->getRecipient('reply_to_email'),
            ['var' => 'test'],
            'Beautiful email subject',
        ];
    }

    /**
     * @dataProvider provideMailData
     */
    public function testCreateForClass(string $mailClass, array $to, ?RecipientInterface $replyTo, array $templateVars, string $subject = null)
    {
        $mail = $this->mailFactory->createForClass($mailClass, $to, $replyTo, $templateVars, $subject);

        $this->assertInstanceOf($mailClass, $mail);
        $this->assertSame('test', $mail->getApp());
        $this->assertSame($to, $mail->getToRecipients());
        $this->assertSame($replyTo, $mail->getReplyTo());
        $this->assertSame($templateVars, $mail->getTemplateVars());
        $this->assertSame([], $mail->getCcRecipients());
        $this->assertSame([], $mail->getBccRecipients());
        $this->assertSame($subject, $mail->getSubject());
    }

    /**
     * @dataProvider provideMailData
     */
    public function testCreateForClassWithCopyRecipients(string $mailClass, array $to, ?RecipientInterface $replyTo, array $templateVars)
    {
        $ccName = 'cc_name';
        $ccEmail = 'cc_email';
        $bccEmail = 'bcc_email';
        $mailFactory = new MailFactory('test', [[$ccEmail, $ccName]], [[$bccEmail]]);

        $mail = $mailFactory->createForClass($mailClass, $to, $replyTo, $templateVars);

        $this->assertInstanceOf($mailClass, $mail);
        $this->assertSame('test', $mail->getApp());
        $this->assertSame($to, $mail->getToRecipients());
        $this->assertSame($replyTo, $mail->getReplyTo());
        $this->assertSame($templateVars, $mail->getTemplateVars());
        $this->assertEquals([$ccEmail => $this->getRecipient($ccEmail, $ccName)], $mail->getCcRecipients());
        $this->assertEquals([$bccEmail => $this->getRecipient($bccEmail)], $mail->getBccRecipients());
    }

    private function getRecipient(string $email, string $name = null): RecipientInterface
    {
        return new Recipient($email, $name);
    }
}

class TotoMail extends Mail
{
}

class HeahMail extends TotoMail
{
}

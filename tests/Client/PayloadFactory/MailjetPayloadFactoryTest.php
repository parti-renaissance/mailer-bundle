<?php

namespace EnMarche\MailerBundle\Tests\Client\PayloadFactory;

use EnMarche\MailerBundle\Client\MailRequestInterface;
use EnMarche\MailerBundle\Client\PayloadFactory\MailjetPayloadFactory;
use EnMarche\MailerBundle\Entity\Address;
use EnMarche\MailerBundle\Entity\RecipientVars;
use EnMarche\MailerBundle\Tests\Test\DummyMailRequest;
use PHPUnit\Framework\TestCase;

class MailjetPayloadFactoryTest extends TestCase
{
    private $mailjetPayloadFactory;

    protected function setUp()
    {
        $this->mailjetPayloadFactory = new MailjetPayloadFactory();
    }

    protected function tearDown()
    {
        $this->mailjetPayloadFactory = null;
    }

    public function testCreateTransactionalRequestPayload()
    {
        $recipientName = 'recipient_name';
        $recipientEmail = 'recipient_email';
        $recipientVars = ['recipient_var' => 'test'];
        $replyToName = 'reply_to_name';
        $replyToEmail = 'reply_to_email';
        $cc1Name = "cc_1_name";
        $cc1Email = "cc_1_email";
        $cc2Email = "cc_2_email";

        $expectedRequestPayload = [
            'MJ-TemplateID' => 'dummy',
            'MJ-TemplateLanguage' => true,
            'Headers' => ['Reply-To' => \sprintf('"%s" <%s>', $replyToName, $replyToEmail)],
            'To' => \sprintf('"%s" <%s>', $recipientName, $recipientEmail),
            'Vars' => $recipientVars,
            'Cc' => \sprintf('"%s" <%s>, <%s>', $cc1Name, $cc1Email, $cc2Email),
        ];

        $mailRequest = $this->getTransactionalMailRequest(
            [$this->getRecipientVars($recipientEmail, $recipientName, $recipientVars)],
            [
                $this->getAddress($cc1Email, $cc1Name),
                $this->getAddress($cc2Email),
            ],
            [],
            $this->getAddress($replyToEmail, $replyToName)
        );

        $this->assertSame(
            $expectedRequestPayload,
            $this->mailjetPayloadFactory->createRequestPayload($mailRequest)
        );
    }

    private function getTransactionalMailRequest(
        array $to = [],
        array $cc = [],
        array $bcc = [],
        Address $replyTo = null
    ): MailRequestInterface
    {
        return new class($to, $cc, $bcc, $replyTo) extends DummyMailRequest
        {
            public $campaign;

            public function __construct(array $to, array $cc, array $bcc, ?Address $replyTo)
            {
                $this->recipientVars = $to;
                $this->ccRecipients = $cc;
                $this->bccRecipients = $bcc;
                $this->replyTo = $replyTo;
            }
        };
    }

    private function getRecipientVars(string $email, string $name = null, array $templateVars = []): RecipientVars
    {
        return new RecipientVars($this->getAddress($email, $name), $templateVars);
    }

    private function getAddress(string $email, string $name = null): Address
    {
        return new Address($email, $name);
    }
}

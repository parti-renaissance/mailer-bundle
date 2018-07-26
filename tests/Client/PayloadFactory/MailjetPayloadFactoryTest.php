<?php

namespace EnMarche\MailerBundle\Tests\Client\PayloadFactory;

use EnMarche\MailerBundle\Client\MailRequestInterface;
use EnMarche\MailerBundle\Client\PayloadFactory\MailjetPayloadFactory;
use EnMarche\MailerBundle\Entity\Address;
use EnMarche\MailerBundle\Entity\RecipientVars;
use EnMarche\MailerBundle\Tests\Test\DummyMailRequest;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

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

        $mailRequest = $this->getMailRequest(
            [$this->getRecipientVars($recipientEmail, $recipientName, $recipientVars)],
            [
                $this->getAddress($cc1Email, $cc1Name),
                $this->getAddress($cc2Email),
            ],
            [/* no bcc */],
            $this->getAddress($replyToEmail, $replyToName)
        );

        $this->assertSame(
            $expectedRequestPayload,
            $this->mailjetPayloadFactory->createRequestPayload($mailRequest)
        );
    }

    /**
     * @expectedException \EnMarche\MailerBundle\Exception\InvalidMailRequestException
     * @expectedExceptionMessage The mail request (id: 1) has no recipient.
     */
    public function testCreateTransactionalRequestPayloadWithoutRecipients()
    {
        $mailRequest = $this->getMailRequest([/* no recipients */]);

        $this->mailjetPayloadFactory->createRequestPayload($mailRequest);
    }

    /**
     * @expectedException \EnMarche\MailerBundle\Exception\InvalidMailRequestException
     * @expectedExceptionMessage The mail request (id: 1) has no campaign but more than one recipient.
     */
    public function testCreateTransactionalRequestPayloadWithManyRecipients()
    {
        $mailRequest = $this->getMailRequest(
            [
                $this->getRecipientVars('recipient_1_email'),
                $this->getRecipientVars('recipient_2_email'),
            ]
        );

        $this->mailjetPayloadFactory->createRequestPayload($mailRequest);
    }

    public function testCreateCampaignRequestPayload()
    {
        $recipient1Name = 'recipient_1_name';
        $recipient1Email = 'recipient_1_email';
        $recipient1Vars = ['recipient_var' => 'test_1'];
        $recipient2Email = 'recipient_2_email';
        $recipient2Vars = ['recipient_var' => 'test_2'];
        $replyToEmail = 'reply_to_email';

        $expectedRequestPayload = [
            'MJ-TemplateID' => 'dummy',
            'MJ-TemplateLanguage' => true,
            'Headers' => ['Reply-To' => \sprintf('<%s>', $replyToEmail)],
            'Recipients' => [
                [
                    'Email' => $recipient1Email,
                    'Name' => $recipient1Name,
                    'Vars' => $recipient1Vars,
                ],
                [
                    'Email' => $recipient2Email,
                    'Vars' => $recipient2Vars,
                ],
            ],
        ];

        $mailRequest = $this->getMailRequest(
            [
                $this->getRecipientVars($recipient1Email, $recipient1Name, $recipient1Vars),
                $this->getRecipientVars($recipient2Email, null, $recipient2Vars),
            ],
            [/* no cc */],
            [/* no bcc */],
            $this->getAddress($replyToEmail)
        );
        $mailRequest->campaign = $this->createMock(UuidInterface::class);

        $this->assertSame(
            $expectedRequestPayload,
            $this->mailjetPayloadFactory->createRequestPayload($mailRequest)
        );
    }

    /**
     * @expectedException \EnMarche\MailerBundle\Exception\InvalidMailRequestException
     * @expectedExceptionMessage A campaign mail request (id: 1, campaign: "90b92ea1-98a7-4256-ac93-ec159d0e77af") cannot have copy recipients.
     */
    public function testCreateCampaignRequestPayloadWithCcRecipients()
    {
        $mailRequest = $this->getMailRequest(
            [],
            [
                $this->getAddress('cc_email'),
            ]
        );
        $campaign = Uuid::fromString('90b92ea1-98a7-4256-ac93-ec159d0e77af');
        $mailRequest->campaign = $campaign;

        $this->mailjetPayloadFactory->createRequestPayload($mailRequest);
    }

    /**
     * @expectedException \EnMarche\MailerBundle\Exception\InvalidMailRequestException
     * @expectedExceptionMessage A campaign mail request (id: 1, campaign: "90b92ea1-98a7-4256-ac93-ec159d0e77af") cannot have copy recipients.
     */
    public function testCreateCampaignRequestPayloadWithBccRecipients()
    {
        $mailRequest = $this->getMailRequest(
            [],
            [],
            [
                $this->getAddress('bcc_email'),
            ]
        );
        $campaign = Uuid::fromString('90b92ea1-98a7-4256-ac93-ec159d0e77af');
        $mailRequest->campaign = $campaign;

        $this->mailjetPayloadFactory->createRequestPayload($mailRequest);
    }

    private function getMailRequest(
        array $to = [],
        array $cc = [],
        array $bcc = [],
        Address $replyTo = null
    ): MailRequestInterface
    {
        return new class($to, $cc, $bcc, $replyTo) extends DummyMailRequest
        {
            public $id = 1;
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

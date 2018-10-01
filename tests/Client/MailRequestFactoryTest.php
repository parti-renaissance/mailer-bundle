<?php

namespace EnMarche\MailerBundle\Tests\Client;

use EnMarche\MailerBundle\Client\MailRequestFactory;
use EnMarche\MailerBundle\Entity\Address;
use EnMarche\MailerBundle\Entity\MailVars;
use EnMarche\MailerBundle\Entity\RecipientVars;
use EnMarche\MailerBundle\Mail\MailInterface;
use EnMarche\MailerBundle\Mail\Recipient;
use EnMarche\MailerBundle\Repository\AddressRepository;
use EnMarche\MailerBundle\Repository\MailVarsRepository;
use EnMarche\MailerBundle\Test\DummyMail;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class MailRequestFactoryTest extends TestCase
{
    /**
     * @var MockObject|AddressRepository
     */
    private $addressRepository;

    /**
     * @var MockObject|MailVarsRepository
     */
    private $mailVarsepository;
    /**
     * @var MailRequestFactory
     */
    private $mailRequestFactory;

    protected function setUp()
    {
        $this->addressRepository = $this->createMock(AddressRepository::class);
        $this->mailVarsepository = $this->createMock(MailVarsRepository::class);
        $this->mailRequestFactory = new MailRequestFactory($this->addressRepository, $this->mailVarsepository);
    }

    protected function tearDown()
    {
        $this->addressRepository = null;
        $this->mailVarsepository = null;
        $this->mailRequestFactory = null;
    }

    public function testCreateRequestForTransactionalMail()
    {
        $to = [
            ['to_mail', 'to_name'],
        ];
        $replyTo = ['reply_to_mail', 'reply_to_name'];
        $cc = ['cc_mail', 'cc_name'];
        $bcc = ['bcc_mail', 'bcc_name'];
        $recipientVars = [
            ['recipient_var' => 'this is a test'],
        ];
        $templateVars = ['template_var' => 'this is still a test'];

        $mail = $this->getMail($to, $recipientVars, null, $replyTo, $templateVars, [$cc], [$bcc]);

        $this->mailVarsepository->expects($this->never())
            ->method('findOneForCampaign')
        ;
        $this->addressRepository->expects($this->exactly(4))
            ->method('findOneByEmailAndName')
            ->willReturnOnConsecutiveCalls(null, null, null, null)
        ;

        $mailRequest = $this->mailRequestFactory->createRequestForMail($mail);

        $this->assertSame($mail->getApp(), $mailRequest->getApp());
        $this->assertSame($mail->getType(), $mailRequest->getType());
        $this->assertEquals(new Address(...$replyTo), $mailRequest->getReplyTo());
        $this->assertSame($mail->getTemplateName(), $mailRequest->getTemplateName());
        $this->assertSame($mail->getTemplateVars(), $mailRequest->getTemplateVars());
        foreach ($mailRequest->getRecipientVars() as $i => $rv) {
            $recipient = new RecipientVars(new Address(...$to[$i]), $recipientVars[$i]);
            $recipient->setMailRequest($mailRequest);

            $this->assertEquals($recipient, $rv);
        }
        $this->assertSame(1, $mailRequest->getRecipientsCount());
        $this->assertTrue($mailRequest->hasCopyRecipients(), 'Mail request should have copy recipients.');
        foreach ($mailRequest->getCcRecipients() as $ccRecipient) {
            $this->assertEquals(new Address(...$cc), $ccRecipient);
        }
        foreach ($mailRequest->getBccRecipients() as $bccRecipient) {
            $this->assertEquals(new Address(...$bcc), $bccRecipient);
        }
        $this->assertNull($mailRequest->getCampaign(), 'Mail request should not have a campaign.');
        $this->assertNull($mailRequest->getRequestPayload(), 'Mail request should not have a request payload.');
        $this->assertNull($mailRequest->getResponsePayload(), 'Mail request should not have a response payload.');
    }

    public function testCreateRequestForCampaignMail()
    {
        $chunkId = Uuid::uuid4();
        $to = [
            ['to_1_mail', 'to_1_name'],
            ['to_2_mail', 'to_2_name'],
        ];
        $recipientVars = [
            ['recipient_1_var' => 'this is a first test'],
            ['recipient_2_var' => 'this is a second test'],
        ];
        $templateVars = ['template_var' => 'this is still a test'];
        $cc = ['cc_mail', 'cc_name'];

        $mail = $this->getMail($to, $recipientVars, $chunkId, null, $templateVars, [$cc]);
        $mailVars = new MailVars(
            $mail->getApp(),
            $mail->getType(),
            $mail->getTemplateName(),
            null,
            'Sender Name',
            'sender@email.com',
            null,
            $mail->getTemplateVars(),
            [new Address(...$cc)],
            [],
            $mail->getChunkId()
        );

        $this->mailVarsepository->expects($this->once())
            ->method('findOneForCampaign')
            ->willReturn($mailVars)
        ;
        $this->addressRepository->expects($this->exactly(2))
            ->method('findOneByEmailAndName')
        ;

        $mailRequest = $this->mailRequestFactory->createRequestForMail($mail);

        $this->assertSame($mail->getApp(), $mailRequest->getApp());
        $this->assertSame($mail->getType(), $mailRequest->getType());
        $this->assertNull($mailRequest->getReplyTo());
        $this->assertSame($mail->getTemplateName(), $mailRequest->getTemplateName());
        $this->assertSame($mail->getTemplateVars(), $mailRequest->getTemplateVars());
        foreach ($mailRequest->getRecipientVars() as $i => $rv) {
            $recipient = new RecipientVars(new Address(...$to[$i]), $recipientVars[$i]);
            $recipient->setMailRequest($mailRequest);

            $this->assertEquals($recipient, $rv);
        }
        $this->assertSame(2, $mailRequest->getRecipientsCount());
        $this->assertTrue($mailRequest->hasCopyRecipients(), 'Mail request should have copy recipients.');
        foreach ($mailRequest->getCcRecipients() as $ccRecipient) {
            $this->assertEquals(new Address(...$cc), $ccRecipient);
        }
        $this->assertSame($chunkId, $mailRequest->getCampaign(), 'Mail chunk identifier should mark a request in a campaign.');
        $this->assertNull($mailRequest->getRequestPayload(), 'Mail request should not have a request payload.');
        $this->assertNull($mailRequest->getResponsePayload(), 'Mail request should not have a response payload.');
    }

    private function getMail(
        array $to = [],
        array $recipientVars = [],
        UuidInterface $chunkId = null,
        array $replyTo = null,
        array $templateVars = [],
        array $cc = [], array $bcc = []
    ): MailInterface {
        return new class($to, $recipientVars, $chunkId, $replyTo, $templateVars, $cc, $bcc) extends DummyMail {
            public function __construct(array $to, array $recipientVars = [], ?UuidInterface $chunkId, array $replyTo = null, array $templateVars = [], array $cc = [], array $bcc = [])
            {
                $this->chunkId = $chunkId;
                $this->replyTo = $replyTo ? new Recipient(...$replyTo) : null;
                $this->templateVars = $templateVars;

                foreach ($to as $i => $recipient) {
                    $this->toRecipients[] = new Recipient($recipient[0], $recipient[1], $recipientVars[$i]);
                }
                foreach ($cc as $c) {
                    $this->ccRecipients[] = new Recipient(...$c);
                }
                foreach ($bcc as $bc) {
                    $this->bccRecipients[] = new Recipient(...$bc);
                }
            }
        };
    }
}

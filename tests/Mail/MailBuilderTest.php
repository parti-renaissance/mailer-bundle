<?php

namespace EnMarche\MailerBundle\Tests\Mail;

use EnMarche\MailerBundle\Mail\Mail;
use EnMarche\MailerBundle\Mail\MailBuilder;
use EnMarche\MailerBundle\Mail\Recipient;
use EnMarche\MailerBundle\Mail\RecipientInterface;
use PHPUnit\Framework\TestCase;

class MailBuilderTest extends TestCase
{
    public function provideMailClasses()
    {
        yield TotoMail::class => [TotoMail::class];
        yield HeahMail::class => [HeahMail::class];
    }

    /**
     * @dataProvider provideMailClasses
     */
    public function testCreate(string $mailClass)
    {
        $builder = MailBuilder::create($mailClass, 'test');

        $this->assertInstanceOf(MailBuilder::class, $builder);
    }

    /**
     * @expectedException \EnMarche\MailerBundle\Exception\InvalidMailClassException
     * @expectedExceptionMessage The given mail class "EnMarche\MailerBundle\Mail\Mail" must be a child of "EnMarche\MailerBundle\Mail\Mail".
     */
    public function testCannotCreateMail()
    {
        MailBuilder::create(Mail::class, 'test');
    }

    /**
     * @dataProvider provideMailClasses
     */
    public function testGetMail(string $mailClass)
    {
        $builder = MailBuilder::create($mailClass, 'test')
            ->addToRecipient($this->createMock(RecipientInterface::class))
        ;

        $this->assertInstanceOf($mailClass, $builder->getMail());
    }
}

<?php

namespace EnMarche\MailerBundle\Tests\Mail;

use EnMarche\MailerBundle\Mail\Mail;
use EnMarche\MailerBundle\Mail\MailBuilder;
use EnMarche\MailerBundle\Mail\MailInterface;
use EnMarche\MailerBundle\Mail\RecipientInterface;
use PHPUnit\Framework\TestCase;

class MailTest extends TestCase
{
    public function provideTemplateNamesForMailClasses(): iterable
    {
        yield FooMail::class => [FooMail::class, 'foo'];
        yield FooBarMail::class => [FooBarMail::class, 'foo_bar'];
        yield FooBarBaz::class => [FooBarBaz::class, 'foo_bar_baz'];
        yield FooBarMailBaz::class => [FooBarMailBaz::class, 'foo_bar_mail_baz'];
        yield BAZMail::class => [BAZMail::class, 'baz'];
        yield HTMLStuffMail::class => [HTMLStuffMail::class, 'html_stuff'];
        yield FixHTMLStuffMail::class => [FixHTMLStuffMail::class, 'fix_html_stuff'];
        yield Foo1Mail::class => [Foo1Mail::class, 'foo1'];
    }

    /**
     * @dataProvider provideTemplateNamesForMailClasses
     */
    public function testGetTemplateName(string $mailClass, string $expectedTemplateName)
    {
        $mail = $this->getMail($mailClass);

        $this->assertSame($expectedTemplateName, $mail->getTemplateName());
    }

    public function provideMailClasses(): iterable
    {
        yield FooMail::class => [FooMail::class];
        yield FooBarMail::class => [FooBarMail::class];
        yield FooBarBaz::class => [FooBarBaz::class];
        yield FooBarMailBaz::class => [FooBarMailBaz::class];
        yield BAZMail::class => [BAZMail::class];
        yield HTMLStuffMail::class => [HTMLStuffMail::class];
        yield FixHTMLStuffMail::class => [FixHTMLStuffMail::class];
        yield Foo1Mail::class => [Foo1Mail::class];
    }

    /**
     * @dataProvider provideMailClasses
     */
    public function testSerialize(string $mailClass)
    {
        $mail = $this->getMail($mailClass);

        $this->assertSame(
            Mail::class,
            \get_class(\unserialize($mail->serialize())),
            \sprintf('Mails should be serialized as parent "%s" class.', Mail::class)
        );
    }

    public function provideRecipientsCountForChunkSize()
    {
        yield 'Recipients count < chunk size => 1 chunk' => [10, 20, 1];
        yield 'Recipients count == chunk size => 1 chunk' => [10, 10, 1];
        yield 'Recipients count > chunk size => 2 chunks' => [20, 10, 2];
        yield 'Recipients count > chunk size => 3 chunks' => [25, 10, 3];
    }

    /**
     * @dataProvider provideRecipientsCountForChunkSize
     */
    public function testChunk(int $recipientsCount, int $chunkSize, int $expectedChunkCount)
    {
        $mail = $this->getMail(FooMail::class, $this->getRecipients($recipientsCount));

        $this->assertCount($recipientsCount, $mail->getToRecipients());

        $chunkCount = 0;

        foreach ($mail->chunk($chunkSize) as $chunk) {
            $chunkCount++;

            if ($expectedChunkCount < $chunkCount) {
                $this->assertCount($chunkSize, $chunk->getToRecipients());
            }
        }

        $this->assertSame($expectedChunkCount, $chunkCount);

        if ($lastCount = $recipientsCount % $chunkSize) {
            $this->assertCount($lastCount, $chunk->getToRecipients());
        } else {
            $this->assertCount($chunkSize, $chunk->getToRecipients());
        }
    }

    private function getMail(string $mailClass, array $to = []): MailInterface
    {
        if (!$to) {
            $to = $this->getRecipients(1);
        }

        $mail = MailBuilder::create($mailClass, 'test')
            ->setToRecipients($to)
            ->getMail()
        ;

        $this->assertSame(
            $mailClass,
            \get_class($mail),
            \sprintf('"%s" should have returned an instance of "%s".', MailBuilder::class, $mailClass)
        );

        return $mail;
    }

    private function getRecipients(int $count): array
    {
        for ($i = 0; $i < $count; $i++) {
            $recipient = $this->createMock(RecipientInterface::class);
            $recipient->expects($this->any())
                ->method('getEmail')
                ->willReturn("email_$i")
            ;

            $recipients[] = $recipient;
        }

        return $recipients ?? [];
    }
}

class FooMail extends Mail
{
}

class FooBarMail extends FooMail
{
}

class FooBarBaz extends FooMail
{
}

class FooBarMailBaz extends FooMail
{
}

class BAZMail extends FooMail
{
}

class HTMLStuffMail extends FooMail
{
}

class FixHTMLStuffMail extends FooMail
{
}

class Foo1Mail extends FooMail
{
}

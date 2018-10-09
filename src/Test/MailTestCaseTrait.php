<?php

namespace EnMarche\MailerBundle\Test;

use Coduo\PHPMatcher\Factory\SimpleFactory;
use EnMarche\MailerBundle\Mail\MailInterface;
use EnMarche\MailerBundle\Mail\RecipientInterface;
use EnMarche\MailerBundle\MailPost\MailPostInterface;

/**
 * @class \PhpUnit\Framework\TestCase
 */
trait MailTestCaseTrait
{
    /**
     * Should be cleared at tear down if the kernel does not reboot to prevent memory leaks
     *
     * @var DebugMailPost
     */
    private $mailPost;

    public function getMailPost(string $name = null): DebugMailPost
    {
        if (!$this->mailPost) {
            $this->mailPost = self::$kernel->getContainer()->get($name ? "en_marche_mailer.mail_post.$name" : MailPostInterface::class);

            if (!$this->mailPost instanceof DebugMailPost) {
                throw new \LogicException(\sprintf('Expected an instance of "%s", but got "%s". Are you running in test environment? Otherwise you need to explicitly load the bundle config file.', DebugMailPost::class, \get_class($mailPost)));
            }
        }

        return $this->mailPost;
    }

    public function assertMailCount(int $expectedCount, string $mailPostName = null): void
    {
        self::assertCount($expectedCount, $this->getMailPost($mailPostName)->countMails());
    }

    public function assertMailCountForClass(int $expectedCount, string $mailClass, string $mailPostName = null): void
    {
        self::assertSame($expectedCount, $this->getMailPost($mailPostName)->countMailsForClass($mailClass));
    }

    public function assertMailSentForRecipient(string $expectedEmail, string $mailClass = null, string $mailPostName = null): void
    {
        $mailPost = $this->getMailPost($mailPostName);
        $mails = $this->getMails($mailClass, $mailPostName);
        $sent = false;

        foreach ($mails as $mail) {
            foreach ($mail->getToRecipients() as $recipient) {
                if ($expectedEmail === $recipient->getEmail()) {
                    $sent = true;

                    break 2;
                }
            }
        }

        self::assertTrue($sent, \sprintf(
            'No mail%s was sent for "%s" among %d mail(s).',
            $mailClass ? " ($mailClass)" : '',
            $expectedEmail,
            $mailClass ? $mailPost->countMailsForClass($mailClass) : $mailPost->countMails()
        ));
    }

    public function assertMailSentForRecipients(array $expectedEmails, string $mailClass = null, string $mailPostName = null): void
    {
        $mailPost = $this->getMailPost($mailPostName);
        $mails = $this->getMails($mailClass, $mailPostName);

        foreach ($expectedEmails as $i => $expectedEmail) {
            $sent = false;

            foreach ($mails as $mail) {
                foreach ($mail->getToRecipients() as $recipient) {
                    if ($expectedEmail === $recipient->getEmail()) {
                        $sent = true;

                        break 2;
                    }
                }
            }
            if ($sent) {
                unset($expectedEmails[$i]);
            }
        }
        $expectedEmails = \array_filter($expectedEmails);

        self::assertCount(0, $expectedEmails, \sprintf(
            'No mail%s was sent for "%s" among %d mail(s).',
            $mailClass ? " ($mailClass)" : '',
            \implode('", "', $expectedEmails),
            $mailClass ? $mailPost->countMailsForClass($mailClass) : $mailPost->countMails()
        ));
    }

    public function assertMailSentForRecipientContainsVars(string $expectedEmail, array $expectedVars, string $mailClass): void
    {
        $this->assertMailSentForRecipient($expectedEmail, $mailClass);

        $factory = new SimpleFactory();
        $matcher = $factory->createMatcher();

        foreach ($this->getMails($mailClass) as $mail) {
            foreach ($mail->getToRecipients() as $recipient) {
                if ($expectedEmail === $recipient->getEmail()) {
                    $isMatched = $matcher->match(
                        $mailVars = array_merge($mail->getTemplateVars(), $recipient->getTemplateVars()),
                        $expectedVars
                    );
                    self::assertTrue(
                        $isMatched,
                        sprintf(
                            "The mail vars don't match the expected array\nExpected: %s\nActual:%s",
                            var_export($expectedVars, true),
                            var_export($mailVars, true)
                        )
                    );
                }
            }
        }
    }

    public static function assertMessageRecipient(
        string $expectedEmail,
        string $expectedName,
        array $expectedVars,
        RecipientInterface $recipient
    ): void {
        self::assertSame($expectedEmail, $recipient->getEmail());
        self::assertSame($expectedName, $recipient->getName());
        self::assertSame($expectedVars, $recipient->getTemplateVars());
    }

    /**
     * @return MailInterface[]
     */
    public function getMails(?string $mailClass, string $mailPostName = null): array
    {
        $mailPost = $this->getMailPost($mailPostName);

        return $mailClass ? $mailPost->getMailsForClass($mailClass) : $mailPost->getMails();
    }

    protected function clearMails(string $mailPostName = null): void
    {
        if (!$this->mailPost) {
            $this->mailPost = $this->getMailPost($mailPostName);
        }

        $this->mailPost->clearMails();
    }
}

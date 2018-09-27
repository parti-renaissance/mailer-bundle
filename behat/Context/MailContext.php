<?php

namespace EnMarche\MailerBundle\Behat\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Symfony2Extension\Context\KernelDictionary;
use Coduo\PHPMatcher\Factory\SimpleFactory;
use EnMarche\MailerBundle\Mail\MailInterface;
use EnMarche\MailerBundle\Test\DebugMailPost;
use EnMarche\MailerBundle\MailPost\MailPostInterface;

class MailContext extends RawMinkContext
{
    use KernelDictionary;

    /** @var DebugMailPost */
    private $mailPost;

    /**
     * @Given I should have :number email(s)( for class :mailClass)?( sent with :mailPostName)?
     */
    public function iShouldHaveMessages(int $number, string $mailClass = null, string $mailPostName = null): void
    {
        $mailPost = $this->getMailPost($mailPostName);
        $count = $mailClass ? $mailPost->countMails() : $mailPost->countMailsForClass($mailClass);

        if ($number !== $count) {
            throw new \RuntimeException(sprintf('Found %d email(s) instead of %d.', $count, $number));
        }
    }

    /**
     * @Given I should have 1 email :mailClass for :recipient with vars:
     */
    public function iShouldHaveEmailForWithPayload(string $maiClass, string $recipient, TableNode $vars, string $mailPostName = null): void
    {
        $mailPost = $this->getMailPost($mailPostName);

        if (1 !== $count = $mailPost->countMailsForClass($maiClass)) {
            throw new \RuntimeException(sprintf('I found %s email(s) instead of 1', $count));
        }

        $simpleFactory = new SimpleFactory();
        $matcher = $simpleFactory->createMatcher();

        foreach ($mailPost->getMailsForClass($maiClass) as $mail) {
            if (1 !== \count($recipients = $mail->getToRecipients())) {
                throw new \RuntimeException('Mail has no recipient.');
            }
            if ($recipient !== $email = $recipients[0]->getEmail()) {
                throw new \RuntimeException(\sprintf('Expected recipient "%s", but got "%s".', $recipient, $email));
            }
            if (!$matcher->match($actual = \array_merge($mail->getTemplateVars(), $recipients[0]->getTemplateVars()), $vars->getRowsHash())) {
                throw new \RuntimeException(\sprintf('Failed expecting vars, got "%s".', \var_export($actual, true)));
            }
        }
    }

    /**
     * @When I click on the link :templateVars of the last email
     */
    public function iClickOnTheEmailLink($templateVars, $mailPostName = null): void
    {
        $lastMail = $this->getMailPost($mailPostName)->getLastSentMail();

        if (!$lastMail instanceof MailInterface) {
            throw new \RuntimeException(\sprintf('No email was previously sent with mail post "%s".', $mailPostName));
        }

        $recipients = $lastMail->getToRecipients();

        if (!\count($recipients)) {
            throw new \RuntimeException(\sprintf('There is no recipient in the last mail sent with mail post "%s".', $mailPostName));
        }

        $link = $recipients[0]->getTemplateVars()[$templateVars] ?? null;

        if (!$link) {
            throw new \RuntimeException(sprintf(
                'There is no variable or no data called %s. Variables availables are %s.',
                $templateVars,
                \implode(', ', \array_keys($recipients[0]->getTemplateVars()))
            ));
        }

        $this->visitPath($link);
    }

    /**
     * @AfterScenario
     */
    public function clearMails(): void
    {
        if ($this->mailPost) {
            $this->mailPost->clearMails();
        }
    }

    private function getMailPost(string $name = null): DebugMailPost
    {
        if (!$this->mailPost) {
            $this->mailPost = $this->getContainer()->get($name ? "en_marche_mailer.mail_post.$name" : MailPostInterface::class);

            if (!$this->mailPost instanceof DebugMailPost) {
                throw new \LogicException(\sprintf('Expected an instance of "%s", but got "%s". Are you running in test environment? Otherwise you need to explicitly load the bundle config file.', DebugMailPost::class, \get_class($mailPost)));
            }
        }

        return $this->mailPost;
    }
}

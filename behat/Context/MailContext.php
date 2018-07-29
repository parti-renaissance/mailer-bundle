<?php

use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Symfony2Extension\Context\KernelDictionary;
use EnMarche\MailerBundle\Mail\MailInterface;
use EnMarche\MailerBundle\Tests\Test\DebugMailPost;
use EnMarche\MailerBundle\MailPost\MailPostInterface;

class MailContext extends RawMinkContext
{
    use KernelDictionary;

    /**
     * @Given I should have :number email(s)( for class :mailClass)?( sent with :mailPostName)?
     */
    public function iShouldHaveMessages(int $number, string $mailClass = null, string $mailPostName = null)
    {
        $mailPost = $this->getMailPost($mailPostName);
        $count = $mailClass ? $mailPost->getMailsCount() : $mailPost->getMailsCountForClass($mailClass);

        if ($number !== $count) {
            throw new \RuntimeException(sprintf('Found %d email(s) instead of %d.', $count, $number));
        }
    }

    /**
     * @Given I should have 1 email :mailClass( sent with :mailPostName) for :recipient with vars:
     */
    public function iShouldHaveEmailForWithPayload(string $maiClass, string $recipient, array $vars, string $mailPostName = null)
    {
        $mail = null;
        $mailPost = $this->getMailPost($mailPostName);

        if (1 !== $mailPost->getMailsCountForClass($maiClass)) {
            throw new \RuntimeException(sprintf('I found %s email(s) instead of 1', $mailPost->getMailsCount($maiClass)));
        }

        foreach ($mailPost->getMailsForClass($maiClass) as $mail) {
            if (1 !== \count($recipients = $mail->getToRecipients())) {
                throw new \RuntimeException('Mail has no recipient.');
            }
            if ($recipient !== $email = $recipients[0]->getEmail()) {
                throw new \RuntimeException(\sprintf('Expected recipient "%s", but got "%s".', $recipient, $email));
            }
            if ($vars !== $actual = \array_merge($mail->getTemplateVars(), $recipients[0]->getTemplateVars())) {
                throw new \RuntimeException(\sprintf('Failed expecting vars, got "%s".', \serialize($actual)));
            }
        }
    }

    /**
     * @When I click on the email link :templateVars (sent with :mailPostName)?
     */
    public function iClickOnTheEmailLink($templateVars, $mailPostName = null)
    {
        $mailPost = $this->getMailPost($mailPostName);

        $lastMail = $mailPost->getLastSentMail();

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
                implode(', ', array_keys($recipients[0]->getTemplateVars()))
            ));
        }

        $this->visitPath($link);
    }

    private function getMailPost(string $name = null): DebugMailPost
    {
        $mailPost = $this->getContainer()->get($name ? "en_marche_mailer.mailPost.$name" : MailPostInterface::class);

        if (!$mailPost instanceof DebugMailPost) {
            throw new \LogicException(\sprintf('Expected an instance of "%s", but got "%s". Are you running in test environment?', DebugMailPost::class, get_class($mailPost)));
        }

        return $mailPost;
    }
}

<?php

use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Symfony2Extension\Context\KernelDictionary;
use EnMarche\MailerBundle\Mail\MailInterface;
use EnMarche\MailerBundle\Tests\Test\DebugToto;
use EnMarche\MailerBundle\Toto\TotoInterface;

class MailContext extends RawMinkContext
{
    use KernelDictionary;

    /**
     * @Given I should have :number email(s)( for class :mailClass)?( sent with :totoName)?
     */
    public function iShouldHaveMessages(int $number, string $mailClass = null, string $totoName = null)
    {
        $toto = $this->getToto($totoName);
        $count = $mailClass ? $toto->getMailsCount() : $toto->getMailsCountForClass($mailClass);

        if ($number !== $count) {
            throw new \RuntimeException(sprintf('Found %d email(s) instead of %d.', $count, $number));
        }
    }

    /**
     * @Given I should have 1 email :mailClass( sent with :totoName) for :recipient with vars:
     */
    public function iShouldHaveEmailForWithPayload(string $maiClass, string $recipient, array $vars, string $totoName = null)
    {
        $mail = null;
        $toto = $this->getToto($totoName);

        foreach ($this->getToto())

        if (1 !== $toto->getMailsCountForClass($maiClass)) {
            throw new \RuntimeException(sprintf('I found %s email(s) instead of 1', $toto->getMailsCount($maiClass)));
        }

        foreach ($toto->getMailsForClass($maiClass) as $mail) {
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
     * @When I click on the email link :emailVariableName (sent with :totoName)?
     */
    public function iClickOnTheEmailLink($emailVariableName, $totoName = null)
    {
        $toto = $this->getToto($totoName);

        $lastMail = $toto->getLastSentMail();

        if (!$lastMail instanceof MailInterface) {
            throw new \RuntimeException(\sprintf('No email was previously sent with toto "%s".', $totoName));
        }

        $recipients = $lastMail->getToRecipients();

        if (!\count($recipients)) {
            throw new \RuntimeException(\sprintf('There is no recipient in the last mail sent with toto "%s".', $totoName));
        }

        $link = $recipients[0]->getTemplateVars()[$emailVariableName] ?? null;

        if (!$link) {
            throw new \RuntimeException(sprintf(
                'There is no variable or no data called %s. Variables availables are %s.',
                $emailVariableName,
                implode(', ', array_keys($recipients[0]->getTemplateVars()))
            ));
        }

        $this->visitPath($link);
    }

    private function getToto(string $name = null): DebugToto
    {
        $toto = $this->getContainer()->get($name ? "en_marche_mailer.toto.$name" : TotoInterface::class);

        if (!$toto instanceof DebugToto) {
            throw new \LogicException(\sprintf('Expected an instance of "%s", but got "%s". Are you running in test environment?', DebugToto::class, get_class($toto)));
        }

        return $toto;
    }
}

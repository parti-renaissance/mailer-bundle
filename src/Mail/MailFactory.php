<?php

namespace EnMarche\MailerBundle\Mail;

class MailFactory implements MailFactoryInterface
{
    private $app;
    private $cc;
    private $bcc;

    /**
     * @param string  $app The application key name
     * @param array[] $cc  Each array must contain at least the email, then the name
     * @param array[] $bcc
     */
    public function __construct(string $app, array $cc = [], array $bcc = [])
    {
        $this->app = $app;

        foreach ($cc as $recipient) {
            if (!is_array($recipient)) {
                throw new \InvalidArgumentException(\sprintf('Expected an array, got %s.', \gettype($recipient)));
            }
            $this->cc[] = new Recipient(...$recipient);
        }
        foreach ($bcc as $recipient) {
            if (!is_array($recipient)) {
                throw new \InvalidArgumentException(\sprintf('Expected an array, got %s.', \gettype($recipient)));
            }
            $this->bcc[] = new Recipient(...$recipient);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createForClass(
        string $mailClass,
        ?array $to,
        RecipientInterface $replyTo = null,
        array $templateVars = [],
        array $ccRecipients = [],
        array $bccRecipients = []
    ): MailInterface
    {
        $builder = MailBuilder::create($mailClass, $this->app);

        if ($to) {
            $builder->setToRecipients($to);
        }
        if ($replyTo) {
            $builder->setReplyTo($replyTo);
        }
        if ($templateVars) {
            $builder->setTemplateVars($templateVars);
        }
        if ($cc = $ccRecipients ?: $this->cc) {
            $builder->setCcRecipients($cc);
        }
        if ($bcc = $bccRecipients ?: $this->bcc) {
            $builder->setBccRecipients($bcc);
        }

        return $builder->getMail();
    }
}

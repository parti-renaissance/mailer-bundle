<?php

namespace EnMarche\MailerBundle\Factory;

use EnMarche\MailerBundle\Mail\MailBuilder;
use EnMarche\MailerBundle\Mail\MailInterface;
use EnMarche\MailerBundle\Mail\Recipient;

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
        array $to,
        array $context,
        $replyTo = null,
        MailVarsFactoryInterface $varsFactory = null
    ): MailInterface
    {
        if (!$varsFactory && !\is_subclass_of($mailClass, MailVarsFactoryInterface::class)) {
            throw new \InvalidArgumentException(\sprintf('You must either pass a %s as third argument or let the mail class implements it.', MailVarsFactoryInterface::class));
        }

        $varsFactory = $varsFactory ?: $mailClass;
        $builder = MailBuilder::create($mailClass, $this->app);

        foreach ($to as $recipient) {
            $builder->addToRecipient($varsFactory::createRecipient($recipient, $context));
        }

        if (is_subclass_of($varsFactory, CampaignMailVarsFactoryInterface::class)) {
            $builder->setTemplateVars($varsFactory::createTemplateVars($context));
        }

        if ($this->cc) {
            $builder->setCcRecipients($this->cc);
        }
        if ($this->bcc) {
            $builder->setBccRecipients($this->bcc);
        }
        if ($replyTo) {
            $builder->setReplyTo($varsFactory::createReplyTo($replyTo));
        }

        return $builder->getMail();
    }
}

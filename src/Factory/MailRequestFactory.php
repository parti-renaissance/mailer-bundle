<?php

namespace EnMarche\MailerBundle\Factory;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use EnMarche\MailerBundle\Entity\Address;
use EnMarche\MailerBundle\Entity\MailRequest;
use EnMarche\MailerBundle\Entity\MailVars;
use EnMarche\MailerBundle\Entity\RecipientVars;
use EnMarche\MailerBundle\Mail\MailInterface;
use EnMarche\MailerBundle\Mail\RecipientInterface;
use EnMarche\MailerBundle\Repository\AddressRepository;
use EnMarche\MailerBundle\Repository\MailVarsRepository;

class MailRequestFactory implements MailRequestFactoryInterface
{
    private $addressRepository;
    private $mailVarsRepository;

    public function __construct(AddressRepository $addressRepository, MailVarsRepository $mailVarsRepository)
    {
        $this->addressRepository = $addressRepository;
        $this->mailVarsRepository = $mailVarsRepository;
    }

    public function createRequestForMail(MailInterface $mail): MailRequest
    {
        return new MailRequest(
            $this->createMailVars($mail),
            $this->createRecipientsVars($mail->getToRecipients())
        );
    }

    private function createMailVars(MailInterface $mail): MailVars
    {
        if ($vars = $this->mailVarsRepository->findOneForCampaign($mail->getChunkId())) {
            return $vars;
        }

        $replyTo = $mail->getReplyTo();

        return new MailVars(
            $mail->getApp(),
            $mail->getType(),
            $mail->getTemplateName(),
            $replyTo ? $this->createAddressFromRecipient($replyTo) : null,
            $mail->getTemplateVars(),
            $this->createAddressesFromRecipients($mail->getCcRecipients()),
            $this->createAddressesFromRecipients($mail->getBccRecipients())
        );
    }

    /**
     * @param RecipientInterface[] $recipients
     *
     * @return RecipientVars[]
     */
    private function createRecipientsVars(array $recipients): array
    {
        return \array_map(function (RecipientInterface $recipient) {
            return new RecipientVars(
                $this->createAddressFromRecipient($recipient),
                $recipient->getTemplateVars()
            );
        }, $recipients);
    }

    /**
     * @param RecipientInterface[] $recipients
     *
     * @return Address[]
     */
    private function createAddressesFromRecipients(array $recipients): array
    {
        return \array_map([$this, 'createAddressFromRecipient'], $recipients);
    }

    private function createAddressFromRecipient(RecipientInterface $recipient): Address
    {
        if ($address = $this->addressRepository->findOneForEmail($recipient->getEmail())) {
            return $address;
        }

        return new Address($recipient->getEmail(), $recipient->getName());
    }
}

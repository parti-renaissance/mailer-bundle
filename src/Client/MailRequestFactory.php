<?php

namespace EnMarche\MailerBundle\Client;

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
    private $createdAddresses = [];

    public function __construct(AddressRepository $addressRepository, MailVarsRepository $mailVarsRepository)
    {
        $this->addressRepository = $addressRepository;
        $this->mailVarsRepository = $mailVarsRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function createRequestForMail(MailInterface $mail): MailRequestInterface
    {
        return new MailRequest(
            $this->createMailVars($mail),
            $this->createRecipientsVars($mail->getToRecipients())
        );
    }

    private function createMailVars(MailInterface $mail): MailVars
    {
        $chunkId = $mail->getChunkId();

        if ($chunkId && $vars = $this->mailVarsRepository->findOneForCampaign($chunkId)) {
            return $vars;
        }

        $replyTo = $mail->getReplyTo();
        $sender = $mail->getSender();

        return new MailVars(
            $mail->getApp(),
            $mail->getType(),
            $mail->getTemplateName(),
            $replyTo ? $this->createAddressFromRecipient($replyTo) : null,
            $sender ? $sender->getName() : null,
            $sender ? $sender->getEmail() : null,
            $mail->getSubject(),
            $mail->getTemplateVars(),
            $this->createAddressesFromRecipients($mail->getCcRecipients()),
            $this->createAddressesFromRecipients($mail->getBccRecipients()),
            $chunkId
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
        return $this->createAddress($recipient->getEmail(), $recipient->getName());
    }

    private function createAddress(string $email, string $name = null): Address
    {
        $address = $this->addressRepository->findOneByEmailAndName($email, $name);

        if ($address) {
            return $address;
        }

        if (isset($this->createdAddresses["${email}_${name}"])) {
            return $this->createdAddresses["${email}_${name}"];
        }

        return $this->createdAddresses["${email}_${name}"] = new Address($email, $name);
    }
}

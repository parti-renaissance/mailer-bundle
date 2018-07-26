<?php

namespace EnMarche\MailerBundle\Client\PayloadFactory;

use EnMarche\MailerBundle\Client\MailRequestInterface;
use EnMarche\MailerBundle\Client\PayloadFactoryInterface;
use EnMarche\MailerBundle\Entity\Address;
use EnMarche\MailerBundle\Entity\RecipientVars;
use EnMarche\MailerBundle\Exception\InvalidMailRequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * From a Mailjet perspective, having Cc or Bcc means sending email to only one recipient.
 * Indeed, using "To", "Cc", "Bcc" fields means having only one set of vars (recipient's become globals).
 *
 * When no copy is needed, the field "Recipients" can be used passing a specific set of variables to each entry,
 * but globals vars must be merged in all of them.
 *
 * @link https://dev.mailjet.com/guides/?php#send-api-v3
 */
class MailjetPayloadFactory implements PayloadFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createRequestPayload(MailRequestInterface $mailRequest): array
    {
        $payload = [
            'MJ-TemplateID' => $mailRequest->getTemplateName(),
            'MJ-TemplateLanguage' => true, // allows injecting vars
        ];

        if ($replyTo = $mailRequest->getReplyTo()) {
            $payload['Headers']['Reply-To'] = $this->formatAddress($replyTo);
        }

        if ($mailRequest->getCampaign()) {
            if ($mailRequest->hasCopyRecipients()) {
                throw new InvalidMailRequestException(\sprintf('A campaign mail request (id: %d, campaign: "%s") cannot have copy recipients.', $mailRequest->getId(), $mailRequest->getCampaign()));
            }

            return $this->createCampaignPayload($mailRequest, $payload);
        }

        return $this->createTransactionalPayload($mailRequest, $payload);
    }

    /**
     * {@inheritdoc}
     */
    public function createResponsePayload(ResponseInterface $response): array
    {
        return \GuzzleHttp\json_decode($response->getBody());
    }

    private function createTransactionalPayload(MailRequestInterface $mailRequest, array $payload): array
    {
        $recipientsCount = $mailRequest->getRecipientsCount();

        if ($recipientsCount > 1) {
            throw new InvalidMailRequestException(\sprintf('The mail request (id: %d) has no campaign but more than one recipient.', $mailRequest->getId()));
        }
        if ($recipientsCount === 0) {
            throw new InvalidMailRequestException(\sprintf('The mail request (id: %d) has no recipient.', $mailRequest->getId()));
        }

        foreach ($mailRequest->getRecipientVars() as $recipient) {
            $payload['To'] = $this->formatAddress($recipient->getAddress());
            $payload['Vars'] = $recipient->getTemplateVars();
        }

        if ($ccRecipients = $mailRequest->getCcRecipients()) {
            $payload['Cc'] = \implode(', ', \array_map([$this, 'formatAddress'], $ccRecipients));
        }
        if ($bccRecipients = $mailRequest->getBccRecipients()) {
            $payload['Bcc'] = \implode(', ', \array_map([$this, 'formatAddress'], $bccRecipients));
        }

        return $payload;
    }

    private function createCampaignPayload(MailRequestInterface $mailRequest, array $payload): array
    {
        $templateVars = $mailRequest->getTemplateVars();

        foreach ($mailRequest->getRecipientVars() as $recipient) {
            $payload['Recipients'][] = $this->createRecipient($recipient, $templateVars);
        }

        return $payload;
    }

    private function createRecipient(RecipientVars $recipientVars, array $templateVars): array
    {
        $address = $recipientVars->getAddress();

        $recipient = [
            'Email' => $address->getEmail(),
        ];

        if ($name = $address->getName()) {
            $recipient['Name'] = $name;
        }

        if ($vars = \array_merge($templateVars, $recipientVars->getTemplateVars())) {
            $recipient['Vars'] = $vars;
        }

        return $recipient;
    }

    private function formatAddress(Address $address): string
    {
        if ($name = $address->getName()) {
            return \sprintf('"%s" <%s>', $this->fixMailjetParsing($name), $address->getEmail());
        }

        return \sprintf('<%s>', $address->getEmail());
    }

    private function fixMailjetParsing(?string $string): string
    {
        return \str_replace(',', '', $string);
    }
}
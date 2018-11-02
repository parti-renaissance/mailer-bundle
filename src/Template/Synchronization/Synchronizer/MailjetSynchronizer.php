<?php

namespace EnMarche\MailerBundle\Template\Synchronization\Synchronizer;

use EnMarche\MailerBundle\Entity\Template;
use EnMarche\MailerBundle\Exception\TemplateSyncHttpException;

class MailjetSynchronizer extends AbstractSynchronizer
{
    private const CREATE_TEMPLATE_ENDPOINT = 'REST/template';
    private const UPDATE_TEMPLATE_ENDPOINT_PATTERN = 'REST/template/%s/detailcontent';

    public function sync(Template $template): void
    {
        return;
        $response = $this->apiClient->request(
            'POST',
            self::CREATE_TEMPLATE_ENDPOINT,
            [
                'body' => json_encode(
                    ['Name' => $template->getLastVersion()->getUuid()->toString()]
                ),
            ]
        );

        if ($response->getStatusCode() !== 201) {
            throw new TemplateSyncHttpException(
                sprintf('[Mailjet API] status code: %s, body: %s', $response->getStatusCode(), (string) $response->getBody())
            );
        }

        $result = json_decode($response->getBody(), true);

        if (isset($result['Data'][0]['ID'])) {
            $response = $this->apiClient->request(
                'POST',
                sprintf(self::UPDATE_TEMPLATE_ENDPOINT_PATTERN, $result['Data'][0]['ID']),
                ['body' => $body = $this->createPayload($template)]
            );

            if ($response->getStatusCode() !== 201) {
                throw new TemplateSyncHttpException(
                    sprintf('[Mailjet API] status code: %s, body: %s', $response->getStatusCode(), (string) $response->getBody())
                );
            }
        }
    }

    private function createPayload(Template $template): string
    {
        return json_encode([
            'Html-part' => $template->getLastVersion()->getBody(),
            'Headers' => json_encode([
                'Subject' => $template->getLastVersion()->getSubject(),
            ]),
        ]);
    }
}

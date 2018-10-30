<?php

namespace EnMarche\MailerBundle\Template\Synchronization\Synchronizer;

use EnMarche\MailerBundle\Entity\Template;

class MailjetSynchronizer extends AbstractSynchronizer
{
    private const CREATE_TEMPLATE_ENDPOINT = '/REST/template';

    public function sync(Template $template): void
    {
        $response = $this->apiClient->request(
            'POST',
            self::CREATE_TEMPLATE_ENDPOINT,
            ['body' => ['Name' => $template->getLastVersion()->getUuid()]]
        );

        dump(json_decode($response->getBody(), true));
    }
}

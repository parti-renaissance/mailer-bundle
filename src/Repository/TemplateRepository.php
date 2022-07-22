<?php

namespace EnMarche\MailerBundle\Repository;

use Doctrine\ORM\EntityRepository;
use EnMarche\MailerBundle\Entity\Template;

class TemplateRepository extends EntityRepository
{
    public function findOne(string $appName, string $mailClass): ?Template
    {
        return $this->findOneBy(['appName' => $appName, 'mailClass' => $mailClass]);
    }
}

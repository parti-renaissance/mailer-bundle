<?php

namespace EnMarche\MailerBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use EnMarche\MailerBundle\Entity\MailVars;
use Ramsey\Uuid\UuidInterface;

class MailVarsRepository extends EntityRepository
{
    /**
     * @throws NonUniqueResultException
     */
    public function findOneForCampaign(UuidInterface $campaign): ?MailVars
    {
        return $this->createQueryBuilder('vars')
            ->where('vars.campaign = :campaign')
            ->setParameter('campaign', $campaign->toString())
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}

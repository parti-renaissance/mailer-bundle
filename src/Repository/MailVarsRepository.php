<?php

namespace EnMarche\MailerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use EnMarche\MailerBundle\Entity\MailVars;
use Ramsey\Uuid\UuidInterface;

class MailVarsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, string $entityClass)
    {
        parent::__construct($registry, MailVars::class);
    }

    /**
     * @throws \Doctrine\ORM\NonUniqueResultException
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

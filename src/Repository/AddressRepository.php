<?php

namespace EnMarche\MailerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use EnMarche\MailerBundle\Entity\Address;
use EnMarche\MailerBundle\Mail\RecipientInterface;

class AddressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, string $entityClass)
    {
        parent::__construct($registry, Address::class);
    }

    /**
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findOneForEmail(string $email): ?Address
    {
        return $this->createQueryBuilder('address')
            ->where('address.canonicalEmail = :email')
            ->setParameter('email', Address::canonicalize($email))
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findOneForRecipient(RecipientInterface $recipient)
    {
        return $this->findOneForEmail($recipient->getEmail());
    }
}

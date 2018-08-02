<?php

namespace EnMarche\MailerBundle\Repository;

use Doctrine\ORM\EntityRepository;
use EnMarche\MailerBundle\Entity\Address;
use EnMarche\MailerBundle\Mail\RecipientInterface;

class AddressRepository extends EntityRepository
{
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
        return $this->findOneForEmail($recipient->getEmail(), $recipient->getName());
    }
}

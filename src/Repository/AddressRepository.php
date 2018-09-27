<?php

namespace EnMarche\MailerBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use EnMarche\MailerBundle\Entity\Address;

class AddressRepository extends EntityRepository
{
    /**
     * @throws NonUniqueResultException
     */
    public function findOneByEmailAndName(string $email, string $name = null): ?Address
    {
        $qb = $this
            ->createQueryBuilder('address')
            ->where('address.canonicalEmail = :email')
            ->setParameter('email', Address::canonicalize($email))
        ;

        if ($name) {
            $qb
                ->andWhere('address.name = :name')
                ->setParameter('name', $name)
            ;
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}

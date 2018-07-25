<?php

namespace EnMarche\MailerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="EnMarche\MailerBundle\Repository\AddressRepository")
 * @ORM\Table(
 *     name="addresses",
 *     indexes={
 *         @ORM\Index(name="email_idx",
 *     }
 * )
 */
class Address
{
    /**
     * @var int|null
     *
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned": true})
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string|null
     *
     * @ORM\Column(nullable=true)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column
     */
    private $email;

    /**
     * @var string
     *
     * @ORM\Column(unique=true)
     */
    private $canonicalEmail;

    public function __construct(string $email, string $name = null)
    {
        $this->name = $name;
        $this->email = $email;
        $this->canonicalEmail = self::canonicalize($email);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getCanonicalEmail(): string
    {
        return $this->canonicalEmail;
    }

    public static function canonicalize(string $email): string
    {
        return \mb_strtolower($email);
    }
}

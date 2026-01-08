<?php

namespace App\Entity;

use App\Repository\RegistrationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'registration')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: RegistrationRepository::class)]
class Registration
{
    public const TYPE_CASH = 1;
    public const TYPE_CHECK = 2;
    public const TYPE_LOCAL = 3;
    public const TYPE_CREDIT_CARD = 4;
    public const TYPE_HELLOASSO = 6;
    public const TYPE_DEFAULT = 5;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'date', type: Types::DATETIME_MUTABLE)]
    #[Assert\DateTime]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    #[Assert\DateTime]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'amount', type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'Un montant est requis')]
    private ?string $amount = null;

    #[ORM\Column(name: 'mode', type: Types::INTEGER)]
    #[Assert\NotBlank(message: 'Un mode de paiement est requis')]
    private ?int $mode = null;

    #[ORM\ManyToOne(targetEntity: Membership::class, inversedBy: 'registrations')]
    #[ORM\JoinColumn(name: 'membership_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Membership $membership = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'recordedRegistrations')]
    #[ORM\JoinColumn(name: 'registrar_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $registrar = null;

    #[ORM\OneToOne(targetEntity: HelloassoPayment::class, mappedBy: 'registration', cascade: ['persist'])]
    private ?HelloassoPayment $helloassoPayment = null;

    private $is_new;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    /**
     * Get id
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return self
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Set date
     *
     * @param \DateTimeInterface|null $date
     *
     * @return Registration
     */
    public function setDate(?\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    /**
     * Get date
     *
     * @return \DateTimeInterface|null
     */
    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    /**
     * Set amount
     *
     * @param string|null $amount
     *
     * @return Registration
     */
    public function setAmount(?string $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * Get amount
     *
     * @return string|null
     */
    public function getAmount(): ?string
    {
        return $this->amount;
    }

    /**
     * Set mode
     *
     * @param int $mode
     *
     * @return Registration
     */
    public function setMode(int $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    /**
     * Get mode
     *
     * @return int|null
     */
    public function getMode(): ?int
    {
        return $this->mode;
    }

    /**
     * Set registrar
     *
     * @param User|null $registrar
     *
     * @return Registration
     */
    public function setRegistrar(?User $registrar = null): self
    {
        $this->registrar = $registrar;

        return $this;
    }

    /**
     * Get registrar
     *
     * @return User|null
     */
    public function getRegistrar(): ?User
    {
        return $this->registrar;
    }

    /**
     * @return mixed
     */
    public function getIsNew()
    {
        return $this->is_new;
    }

    /**
     * @param mixed $value
     * @return self
     */
    public function setIsNew($value): self
    {
        $this->is_new = $value;

        return $this;
    }

    /**
     * Set createdAt
     *
     * @param \DateTimeInterface|null $createdAt
     *
     * @return Registration
     */
    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt
     *
     * @return \DateTimeInterface|null
     */
    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Set helloassoPayment.
     *
     * @param HelloassoPayment|null $helloassoPayment
     *
     * @return Registration
     */
    public function setHelloassoPayment(?HelloassoPayment $helloassoPayment = null): self
    {
        $this->helloassoPayment = $helloassoPayment;

        return $this;
    }

    /**
     * Get helloassoPayment.
     *
     * @return HelloassoPayment|null
     */
    public function getHelloassoPayment(): ?HelloassoPayment
    {
        return $this->helloassoPayment;
    }

    /**
     * @return Membership|null
     */
    public function getMembership(): ?Membership
    {
        return $this->membership;
    }

    /**
     * @param Membership|null $membership
     */
    public function setMembership(?Membership $membership): void
    {
        $this->membership = $membership;
    }
}

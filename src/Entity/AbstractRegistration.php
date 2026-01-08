<?php

namespace App\Entity;

use App\Repository\AbstractRegistrationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'view_abstract_registration')]
#[ORM\Entity(readOnly: true)]
class AbstractRegistration
{
    public const TYPE_ANONYMOUS = 2;
    public const TYPE_MEMBER = 1;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'string', length: 191)]
    private string $id;

    #[ORM\Column(name: 'type', type: 'integer')]
    private int $type;

    #[ORM\Column(name: 'date', type: 'datetime')]
    private \DateTimeInterface $date;

    #[ORM\Column(name: 'start_date', type: 'datetime')]
    private \DateTimeInterface $start_date;

    #[ORM\Column(name: 'amount', type: 'string', length: 255)]
    private string $amount;

    #[ORM\Column(name: 'mode', type: 'integer')]
    private int $mode;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'recordedRegistrations')]
    #[ORM\JoinColumn(name: 'registrar_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $registrar = null;

    #[ORM\Column(name: 'beneficiary', type: 'string', length: 255)]
    private string $beneficiary;

    #[ORM\ManyToOne(targetEntity: Membership::class)]
    #[ORM\JoinColumn(name: 'membership_id', referencedColumnName: 'id')]
    private ?Membership $membership = null;

    /**
     * Get id
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get date
     *
     * @return \DateTimeInterface
     */
    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    /**
     * Get start date
     *
     * @return \DateTimeInterface|null
     */
    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->start_date;
    }

    /**
     * Get amount
     *
     * @return string
     */
    public function getAmount(): string
    {
        return $this->amount;
    }

    /**
     * Get mode
     *
     * @return int
     */
    public function getMode(): int
    {
        return $this->mode;
    }

    /**
     * Get type
     *
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
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
     * Get beneficiary
     *
     * @return string
     */
    public function getBeneficiary(): string
    {
        return $this->beneficiary;
    }

    /**
     * @return Membership|null
     */
    public function getMembership(): ?Membership
    {
        return $this->membership;
    }

    public function toRegistration(): Registration
    {
        $registration = new Registration();
        $registration->setRegistrar($this->registrar);
        $registration->setMode($this->mode);
        $registration->setAmount($this->amount);
        $registration->setDate($this->date);
        return $registration;
    }

    public function getEntityId(): string
    {
        return substr($this->getId(), 2);
    }
}

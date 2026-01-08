<?php

namespace App\Entity;

use App\Repository\MembershipShiftExemptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Table(name: 'membership_shift_exemption')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: MembershipShiftExemptionRepository::class)]
#[UniqueEntity(fields: ['membership', 'start'])]
#[UniqueEntity(fields: ['membership', 'end'])]
class MembershipShiftExemption
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: ShiftExemption::class, inversedBy: 'membershipShiftExemptions')]
    #[ORM\JoinColumn(name: 'shift_exemption_id', referencedColumnName: 'id')]
    private ?ShiftExemption $shiftExemption = null;

    #[Assert\NotBlank]
    #[ORM\Column(name: 'description', type: Types::STRING, length: 255, nullable: false)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: Membership::class, inversedBy: 'membershipShiftExemptions')]
    #[ORM\JoinColumn(name: 'membership_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    private ?Membership $membership = null;

    #[Assert\Date]
    #[ORM\Column(name: 'start', type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $start = null;

    #[Assert\Date]
    #[ORM\Column(name: 'end', type: Types::DATE_MUTABLE)]
    #[Assert\GreaterThan(propertyPath: 'start')]
    private ?\DateTimeInterface $end = null;

    /**
     * Get id.
     *
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    #[ORM\PrePersist]
    public function setCreatedAt()
    {
        $this->createdAt = new \DateTime();
    }

    /**
     * Get createdAt.
     *
     * @return \DateTimeInterface
     */
    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Set createdBy.
     *
     * @param User|null $createdBy
     *
     * @return MembershipShiftExemption
     */
    public function setCreatedBy(?User $createdBy = null): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    /**
     * Get createdBy.
     *
     * @return User|null
     */
    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    /**
     * Set shiftExemption.
     *
     * @param ShiftExemption $shiftExemption
     *
     * @return MembershipShiftExemption
     */
    public function setShiftExemption(ShiftExemption $shiftExemption): self
    {
        $this->shiftExemption = $shiftExemption;

        return $this;
    }

    /**
     * Get shiftExemption.
     *
     * @return ShiftExemption|null
     */
    public function getShiftExemption(): ?ShiftExemption
    {
        return $this->shiftExemption;
    }

    /**
     * Set description.
     *
     * @param string $description
     *
     * @return MembershipShiftExemption
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description.
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Get membership.
     *
     * @return Membership|null
     */
    public function getMembership(): ?Membership
    {
        return $this->membership;
    }

    /**
     * Set membership.
     *
     * @param Membership|null $membership
     * @return self
     */
    public function setMembership(?Membership $membership): self
    {
        $this->membership = $membership;
        return $this;
    }

    /**
     * Set start.
     *
     * @param \DateTimeInterface|null $start
     *
     * @return MembershipShiftExemption
     */
    public function setStart(?\DateTimeInterface $start): self
    {
        $this->start = $start;

        return $this;
    }

    /**
     * Get start.
     *
     * @return \DateTimeInterface|null
     */
    public function getStart(): ?\DateTimeInterface
    {
        return $this->start;
    }

    /**
     * Set end.
     *
     * @param \DateTimeInterface|null $end
     *
     * @return MembershipShiftExemption
     */
    public function setEnd(?\DateTimeInterface $end): self
    {
        $this->end = $end;

        return $this;
    }

    /**
     * Get end.
     *
     * @return \DateTimeInterface|null
     */
    public function getEnd(): ?\DateTimeInterface
    {
        return $this->end;
    }

    #[Assert\IsTrue(message: 'La date de début doit être avant celle de fin')]
    public function isStartBeforeEnd(): bool
    {
        return $this->start < $this->end;
    }

    /**
     * Return if the membershipShiftExemption is past for a given date
     *
     * @param \DateTimeInterface|null $date
     * @return boolean
     */
    public function isPast(?\DateTimeInterface $date = null): bool
    {
        if (!$date) {
            $date = new \DateTime('now');
        }
        return $date > $this->end;
    }

    /**
     * Return if the membershipShiftExemption is upcoming for a given date
     *
     * @param \DateTimeInterface|null $date
     * @return boolean
     */
    public function isUpcoming(?\DateTimeInterface $date = null): bool
    {
        if (!$date) {
            $date = new \DateTime('now');
        }
        return $date < $this->start;
    }

    /**
     * Return if the membershipShiftExemption is current (ongoing) for a given date
     *
     * @param \DateTimeInterface|null $date
     * @return boolean
     */
    public function isCurrent(?\DateTimeInterface $date = null): bool
    {
        if (!$date) {
            $date = new \DateTime('now');
        }
        return !$this->isPast($date) && !$this->isUpcoming($date);
    }
}

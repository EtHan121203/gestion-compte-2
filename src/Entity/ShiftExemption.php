<?php

namespace App\Entity;

use App\Repository\ShiftExemptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'shift_exemption')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: ShiftExemptionRepository::class)]
class ShiftExemption
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: 'string', length: 255, unique: true)]
    private ?string $name = null;

    /** @var Collection<int, MembershipShiftExemption> */
    #[ORM\OneToMany(targetEntity: MembershipShiftExemption::class, mappedBy: 'shiftExemption', cascade: ['persist'])]
    private Collection $membershipShiftExemptions;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->membershipShiftExemptions = new ArrayCollection();
    }

    /**
     * Define toString.
     *
     * @return string
     */
    public function __toString(): string
    {
        return (string) $this->name;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTime();
        }
    }

    /**
     * Get id.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set name.
     *
     * @param string|null $name
     *
     * @return ShiftExemption
     */
    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Get membershipShiftExemptions
     *
     * @return Collection<int, MembershipShiftExemption>
     */
    public function getMembershipShiftExemptions(): Collection
    {
        return $this->membershipShiftExemptions;
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
}

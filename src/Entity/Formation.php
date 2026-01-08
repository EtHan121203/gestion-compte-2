<?php

namespace App\Entity;

use App\Repository\FormationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Table(name: 'formation')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: FormationRepository::class)]
#[UniqueEntity(fields: ['name'], message: 'Ce nom est déjà utilisé par une autre formation')]
class Formation
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    protected ?int $id = null;

    #[ORM\Column(name: 'name', type: 'string', length: 180, unique: true)]
    private ?string $name = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * @var Collection<int, Beneficiary>
     */
    #[ORM\ManyToMany(targetEntity: Beneficiary::class, mappedBy: 'formations')]
    private Collection $beneficiaries;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct(?string $name = null, array $roles = [])
    {
        $this->name = $name;
        $this->roles = $roles;
        $this->beneficiaries = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string)$this->getName();
    }

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get description
     * 
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description ?? '';
    }

    /**
     * Set description
     * 
     * @param string|null $description
     * @return self
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get roles
     *
     * @return array
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
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
     * Add beneficiary
     *
     * @param Beneficiary $beneficiary
     *
     * @return self
     */
    public function addBeneficiary(Beneficiary $beneficiary): self
    {
        if (!$this->beneficiaries->contains($beneficiary)) {
            $this->beneficiaries[] = $beneficiary;
            $beneficiary->addFormation($this);
        }

        return $this;
    }

    /**
     * Remove beneficiary
     *
     * @param Beneficiary $beneficiary
     *
     * @return self
     */
    public function removeBeneficiary(Beneficiary $beneficiary): self
    {
        if ($this->beneficiaries->removeElement($beneficiary)) {
            $beneficiary->removeFormation($this);
        }

        return $this;
    }

    /**
     * Get beneficiaries
     *
     * @return Collection<int, Beneficiary>
     */
    public function getBeneficiaries(): Collection
    {
        return $this->beneficiaries;
    }
}

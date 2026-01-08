<?php

namespace App\Entity;

use App\Repository\TaskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'task')]
#[ORM\Entity(repositoryClass: TaskRepository::class)]
class Task
{
    public const PRIORITY_URGENT_VALUE = 5;
    public const PRIORITY_URGENT_COLOR = "red white-text";
    public const PRIORITY_IMPORTANT_VALUE = 4;
    public const PRIORITY_IMPORTANT_COLOR = "orange white-text";
    public const PRIORITY_NORMAL_VALUE = 3;
    public const PRIORITY_NORMAL_COLOR = "brown white-text";
    public const PRIORITY_ANNEXE_VALUE = 2;
    public const PRIORITY_ANNEXE_COLOR = "gray black-text";

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'title', type: 'string', length: 255)]
    private ?string $title = null;

    #[ORM\Column(name: 'due_date', type: 'date', nullable: true)]
    private ?\DateTimeInterface $dueDate = null;

    #[ORM\Column(name: 'closed', type: 'boolean', nullable: true, options: ['default' => 0])]
    private ?bool $closed = null;

    /** @var Collection<int, Commission> */
    #[ORM\ManyToMany(targetEntity: Commission::class, inversedBy: 'tasks')]
    #[ORM\JoinTable(name: 'tasks_commissions')]
    private Collection $commissions;

    /** @var Collection<int, Beneficiary> */
    #[ORM\ManyToMany(targetEntity: Beneficiary::class, inversedBy: 'tasks', cascade: ['persist'])]
    #[ORM\JoinTable(name: 'tasks_beneficiaries')]
    private Collection $owners;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'registrar_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $registrar = null;

    #[ORM\Column(name: 'priority', type: 'smallint')]
    private ?int $priority = null;

    #[ORM\Column(name: 'status', type: 'string', length: 255, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->commissions = new ArrayCollection();
        $this->owners = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setPriority(?int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function addCommission(Commission $commission): self
    {
        if (!$this->commissions->contains($commission)) {
            $this->commissions[] = $commission;
        }

        return $this;
    }

    public function removeCommission(Commission $commission): void
    {
        $this->commissions->removeElement($commission);
    }

    /** @return Collection<int, Commission> */
    public function getCommissions(): Collection
    {
        return $this->commissions;
    }

    public function addOwner(Beneficiary $owner): self
    {
        if (!$this->owners->contains($owner)) {
            $this->owners[] = $owner;
            $owner->addTask($this);
        }

        return $this;
    }

    public function removeOwner(Beneficiary $owner): void
    {
        $this->owners->removeElement($owner);
    }

    /** @return Collection<int, Beneficiary> */
    public function getOwners(): Collection
    {
        return $this->owners;
    }

    public function setRegistrar(?User $registrar = null): self
    {
        $this->registrar = $registrar;

        return $this;
    }

    public function getRegistrar(): ?User
    {
        return $this->registrar;
    }

    public function setDueDate(?\DateTimeInterface $dueDate): self
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setClosed(?bool $closed): self
    {
        $this->closed = $closed;

        return $this;
    }

    public function getClosed(): ?bool
    {
        return $this->closed;
    }
}

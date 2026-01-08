<?php

namespace App\Entity;

use App\Repository\CommissionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'commission')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: CommissionRepository::class)]
class Commission
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\Column(name: 'description', type: 'text')]
    private ?string $description = null;

    #[ORM\Column(name: 'email', type: 'string', length: 255)]
    private ?string $email = null;

    #[ORM\Column(name: 'next_meeting_desc', type: 'string', length: 255, nullable: true)]
    private ?string $next_meeting_desc = null;

    #[ORM\Column(name: 'next_meeting_date', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $next_meeting_date = null;

    /**
     * @var Collection<int, Beneficiary>
     */
    #[ORM\ManyToMany(targetEntity: Beneficiary::class, mappedBy: 'commissions')]
    private Collection $beneficiaries;

    /**
     * @var Collection<int, Task>
     */
    #[ORM\ManyToMany(targetEntity: Task::class, mappedBy: 'commissions')]
    #[ORM\OrderBy(['closed' => 'ASC', 'dueDate' => 'ASC'])]
    private Collection $tasks;

    /**
     * @var Collection<int, Beneficiary>
     */
    #[ORM\OneToMany(targetEntity: Beneficiary::class, mappedBy: 'own', cascade: ['persist'])]
    private Collection $owners;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->beneficiaries = new ArrayCollection();
        $this->tasks = new ArrayCollection();
        $this->owners = new ArrayCollection();
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

    /**
     * Set name
     *
     * @param string|null $name
     *
     * @return self
     */
    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
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
            $beneficiary->addCommission($this);
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
            $beneficiary->removeCommission($this);
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

    /**
     * Set description
     *
     * @param string|null $description
     *
     * @return self
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Add owner
     *
     * @param Beneficiary $owner
     *
     * @return self
     */
    public function addOwner(Beneficiary $owner): self
    {
        if (!$this->owners->contains($owner)) {
            $this->owners[] = $owner;
            $owner->setOwn($this);
        }

        return $this;
    }

    /**
     * Remove owner
     *
     * @param Beneficiary $owner
     *
     * @return self
     */
    public function removeOwner(Beneficiary $owner): self
    {
        if ($this->owners->removeElement($owner)) {
            // set the owning side to null (unless already changed)
            if ($owner->getOwn() === $this) {
                $owner->setOwn(null);
            }
        }

        return $this;
    }

    /**
     * Get owners
     *
     * @return Collection<int, Beneficiary>
     */
    public function getOwners(): Collection
    {
        return $this->owners;
    }

    /**
     * Set email
     *
     * @param string|null $email
     *
     * @return self
     */
    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email
     *
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Add task
     *
     * @param Task $task
     *
     * @return self
     */
    public function addTask(Task $task): self
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks[] = $task;
            $task->addCommission($this);
        }

        return $this;
    }

    /**
     * Remove task
     *
     * @param Task $task
     *
     * @return self
     */
    public function removeTask(Task $task): self
    {
        if ($this->tasks->removeElement($task)) {
            $task->removeCommission($this);
        }

        return $this;
    }

    /**
     * Get tasks
     *
     * @return Collection<int, Task>
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    /**
     * Set nextMeetingDesc.
     *
     * @param string|null $nextMeetingDesc
     *
     * @return self
     */
    public function setNextMeetingDesc(?string $nextMeetingDesc): self
    {
        $this->next_meeting_desc = $nextMeetingDesc;

        return $this;
    }

    /**
     * Get nextMeetingDesc.
     *
     * @return string|null
     */
    public function getNextMeetingDesc(): ?string
    {
        return $this->next_meeting_desc;
    }

    /**
     * Set nextMeetingDate.
     *
     * @param \DateTimeInterface|null $nextMeetingDate
     *
     * @return self
     */
    public function setNextMeetingDate(?\DateTimeInterface $nextMeetingDate): self
    {
        $this->next_meeting_date = $nextMeetingDate;

        return $this;
    }

    /**
     * Get nextMeetingDate.
     *
     * @return \DateTimeInterface|null
     */
    public function getNextMeetingDate(): ?\DateTimeInterface
    {
        return $this->next_meeting_date;
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

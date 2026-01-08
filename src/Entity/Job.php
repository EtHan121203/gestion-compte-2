<?php

namespace App\Entity;

use App\Repository\JobRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'job')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: JobRepository::class)]
class Job
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: 'string', length: 191, unique: true)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(name: 'color', type: 'string', length: 255, unique: false)]
    #[Assert\NotBlank]
    private ?string $color = null;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'url', type: 'string', length: 255, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(name: 'min_shifter_alert', type: 'integer', options: ['default' => 2])]
    private int $min_shifter_alert = 2;

    /**
     * @var Collection<int, Shift>
     */
    #[ORM\OneToMany(targetEntity: Shift::class, mappedBy: 'job', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $shifts;

    /**
     * @var Collection<int, Period>
     */
    #[ORM\OneToMany(targetEntity: Period::class, mappedBy: 'job', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $periods;

    #[ORM\Column(name: 'enabled', type: 'boolean', nullable: false, options: ['default' => 1])]
    private bool $enabled = true;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->shifts = new ArrayCollection();
        $this->periods = new ArrayCollection();
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
     * Set color
     *
     * @param string|null $color
     *
     * @return self
     */
    public function setColor(?string $color): self
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Get color
     *
     * @return string|null
     */
    public function getColor(): ?string
    {
        return $this->color;
    }

    /**
     * Get min_shifter_alert
     *
     * @return int
     */
    public function getMinShifterAlert(): int
    {
        return $this->min_shifter_alert;
    }

    /**
     * Set min_shifter_alert
     * 
     * @param int $min_shifter_alert
     * @return self
     */
    public function setMinShifterAlert(int $min_shifter_alert): self
    {
        $this->min_shifter_alert = $min_shifter_alert;
        return $this;
    }

    /**
     * Add shift
     *
     * @param Shift $shift
     *
     * @return self
     */
    public function addShift(Shift $shift): self
    {
        if (!$this->shifts->contains($shift)) {
            $this->shifts[] = $shift;
            $shift->setJob($this);
        }

        return $this;
    }

    /**
     * Remove shift
     *
     * @param Shift $shift
     *
     * @return self
     */
    public function removeShift(Shift $shift): self
    {
        if ($this->shifts->removeElement($shift)) {
            // set the owning side to null (unless already changed)
            if ($shift->getJob() === $this) {
                $shift->setJob(null);
            }
        }

        return $this;
    }

    /**
     * Get shifts
     *
     * @return Collection<int, Shift>
     */
    public function getShifts(): Collection
    {
        return $this->shifts;
    }

    /**
     * Add period
     *
     * @param Period $period
     *
     * @return self
     */
    public function addPeriod(Period $period): self
    {
        if (!$this->periods->contains($period)) {
            $this->periods[] = $period;
            $period->setJob($this);
        }

        return $this;
    }

    /**
     * Remove period
     *
     * @param Period $period
     *
     * @return self
     */
    public function removePeriod(Period $period): self
    {
        if ($this->periods->removeElement($period)) {
            // set the owning side to null (unless already changed)
            if ($period->getJob() === $this) {
                $period->setJob(null);
            }
        }

        return $this;
    }

    /**
     * Get periods
     *
     * @return Collection<int, Period>
     */
    public function getPeriods(): Collection
    {
        return $this->periods;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     * @return self
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
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
     * Set url
     *
     * @param string|null $url
     *
     * @return self
     */
    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Get url
     *
     * @return string|null
     */
    public function getUrl(): ?string
    {
        return $this->url;
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

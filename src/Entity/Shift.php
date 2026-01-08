<?php

namespace App\Entity;

use App\Repository\ShiftRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'shift')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: ShiftRepository::class)]
class Shift
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'start', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $start = null;

    #[ORM\Column(name: 'end', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $end = null;

    #[ORM\Column(name: 'booked_time', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $bookedTime = null;

    #[ORM\Column(name: 'was_carried_out', type: Types::BOOLEAN, options: ['default' => 0])]
    private ?bool $wasCarriedOut = null;

    #[ORM\ManyToOne(targetEntity: Beneficiary::class, inversedBy: 'shifts')]
    #[ORM\JoinColumn(name: 'shifter_id', referencedColumnName: 'id')]
    private ?Beneficiary $shifter = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'booker_id', referencedColumnName: 'id')]
    private ?User $booker = null;

    #[ORM\ManyToOne(targetEntity: Beneficiary::class, inversedBy: 'reservedShifts')]
    #[ORM\JoinColumn(name: 'last_shifter_id', referencedColumnName: 'id')]
    private ?Beneficiary $lastShifter = null;

    #[ORM\ManyToOne(targetEntity: Formation::class)]
    #[ORM\JoinColumn(name: 'formation_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Formation $formation = null;

    #[ORM\ManyToOne(targetEntity: Job::class, inversedBy: 'shifts')]
    #[ORM\JoinColumn(name: 'job_id', referencedColumnName: 'id', nullable: false)]
    private ?Job $job = null;

    #[ORM\ManyToOne(targetEntity: PeriodPosition::class)]
    #[ORM\JoinColumn(name: 'position_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?PeriodPosition $position = null;

    #[ORM\OneToMany(targetEntity: TimeLog::class, mappedBy: 'shift')]
    private Collection $timeLogs;

    #[ORM\OneToMany(targetEntity: ShiftFreeLog::class, mappedBy: 'shift')]
    private Collection $shiftFreeLogs;

    #[ORM\Column(name: 'locked', type: Types::BOOLEAN, options: ['default' => 0], nullable: false)]
    private ?bool $locked = false;

    #[ORM\Column(name: 'fixe', type: Types::BOOLEAN, options: ['default' => 0], nullable: false)]
    private ?bool $fixe = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->wasCarriedOut = false;
        $this->timeLogs = new ArrayCollection();
        $this->shiftFreeLogs = new ArrayCollection();
    }

    public function __toString(): string
    {
        setlocale(LC_TIME, 'fr_FR.UTF8');
        return strftime("%A %e %B de %R", $this->getStart()->getTimestamp()) . ' à ' . strftime("%R", $this->getEnd()->getTimestamp()) . ' [' . $this->getShifter() . ']';
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
     * Set start
     *
     * @param \DateTimeInterface|null $start
     *
     * @return Shift
     */
    public function setStart(?\DateTimeInterface $start): self
    {
        $this->start = $start;

        return $this;
    }

    /**
     * Get start
     *
     * @return \DateTimeInterface|null
     */
    public function getStart(): ?\DateTimeInterface
    {
        return $this->start;
    }

    /**
     * Set end
     *
     * @param \DateTimeInterface|null $end
     *
     * @return Shift
     */
    public function setEnd(?\DateTimeInterface $end): self
    {
        $this->end = $end;

        return $this;
    }

    /**
     * Get end
     *
     * @return \DateTimeInterface|null
     */
    public function getEnd(): ?\DateTimeInterface
    {
        return $this->end;
    }

    /**
     * Set bookedTime
     *
     * @param \DateTimeInterface|null $bookedTime
     *
     * @return Shift
     */
    public function setBookedTime(?\DateTimeInterface $bookedTime): self
    {
        $this->bookedTime = $bookedTime;

        return $this;
    }

    /**
     * Get bookedTime
     *
     * @return \DateTimeInterface|null
     */
    public function getBookedTime(): ?\DateTimeInterface
    {
        return $this->bookedTime;
    }

    /**
     * Set wasCarriedOut
     *
     * @param bool $wasCarriedOut
     *
     * @return Shift
     */
    public function setWasCarriedOut(bool $wasCarriedOut): self
    {
        $this->wasCarriedOut = $wasCarriedOut;

        return $this;
    }

    /**
     * Validate shift participation
     *
     * @return Shift
     */
    public function validateShiftParticipation(): self
    {
        $this->setWasCarriedOut(true);

        return $this;
    }

    /**
     * Invalidate shift participation
     *
     * @return Shift
     */
    public function invalidateShiftParticipation(): self
    {
        $this->setWasCarriedOut(false);

        return $this;
    }

    /**
     * Get wasCarriedOut
     *
     * @return bool|null
     */
    public function getWasCarriedOut(): ?bool
    {
        return $this->wasCarriedOut;
    }

    /**
     * Set booker
     *
     * @param User|null $booker
     *
     * @return Shift
     */
    public function setBooker(?User $booker = null): self
    {
        $this->booker = $booker;

        return $this;
    }

    /**
     * Get booker
     *
     * @return User|null
     */
    public function getBooker(): ?User
    {
        return $this->booker;
    }

    /**
     * Set shifter
     *
     * @param Beneficiary|null $shifter
     *
     * @return Shift
     */
    public function setShifter(?Beneficiary $shifter = null): self
    {
        $this->shifter = $shifter;

        return $this;
    }

    /**
     * Get shifter
     *
     * @return Beneficiary|null
     */
    public function getShifter(): ?Beneficiary
    {
        return $this->shifter;
    }


    public function getDuration(): int
    {
        $diff = date_diff($this->start, $this->end);
        return $diff->h * 60 + $diff->i;
    }

    public function getIntervalCode(): string
    {
        return $this->start->format("h-i") . $this->end->format("h-i");
    }

    /**
     * Set formation
     *
     * @param Formation|null $formation
     *
     * @return Shift
     */
    public function setFormation(?Formation $formation = null): self
    {
        $this->formation = $formation;

        return $this;
    }

    /**
     * Get formation
     *
     * @return Formation|null
     */
    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    /**
     * Set job
     *
     * @param Job|null $job
     *
     * @return Shift
     */
    public function setJob(?Job $job = null): self
    {
        $this->job = $job;

        return $this;
    }

    /**
     * Get job
     *
     * @return Job|null
     */
    public function getJob(): ?Job
    {
        return $this->job;
    }

    /**
     * free // unbook
     *
     * @return Shift
     */
    public function free(): self
    {
        $this->setBooker(null);
        $this->setBookedTime(null);
        $this->setShifter(null);
        $this->setFixe(false);
        return $this;
    }

    /**
     * Return true if the shift is in the past
     *
     * @return bool
     */
    public function getIsPast(): bool
    {
        $now = new \DateTime('now');
        return $this->end < $now;
    }

    /**
     * Return true if the shift is now
     *
     * @return bool
     */
    public function getIsCurrent(): bool
    {
        $now = new \DateTime('now');
        return ($this->start < $now) && ($now < $this->end);
    }

    /**
     * Return true if the shift is now or in the past
     *
     * @return bool
     */
    public function getIsPastOrCurrent(): bool
    {
        return ($this->getIsPast() or $this->getIsCurrent());
    }

    /**
     * Return true if the shift is in the future
     *
     * @return bool
     */
    public function getIsFuture(): bool
    {
        return !$this->getIsPastOrCurrent();
    }

    /**
     * Return true if the shift is not in the past, not current, and close enough
     *
     * @return bool
     */
    public function getIsUpcoming(): bool
    {
        return $this->isBefore('2 days');
    }

    /**
     * Return true if the shift starts before the duration given as parameter
     *
     * @param string $duration
     *
     * @return bool
     */
    public function isBefore(string $duration): bool
    {
        $futureDate = new \DateTime($duration);
        $futureDate->setTime(23, 59, 59);
        return $this->getIsFuture() && ($this->start < $futureDate);
    }

    /**
     * Set lastShifter
     *
     * @param Beneficiary|null $lastShifter
     *
     * @return Shift
     */
    public function setLastShifter(?Beneficiary $lastShifter = null): self
    {
        $this->lastShifter = $lastShifter;

        return $this;
    }

    /**
     * Get lastShifter
     *
     * @return Beneficiary|null
     */
    public function getLastShifter(): ?Beneficiary
    {
        return $this->lastShifter;
    }

    public function getTmpToken(string $key = ''): string
    {
        return md5($this->getId() . $this->getStart()->format('d/m/Y') . $this->getEnd()->format('d/m/Y') . $key);
    }

    /**
     * Add timeLog
     *
     * @param TimeLog $timeLog
     *
     * @return Shift
     */
    public function addTimeLog(TimeLog $timeLog): self
    {
        $this->timeLogs[] = $timeLog;

        return $this;
    }

    /**
     * Remove timeLog
     *
     * @param TimeLog $timeLog
     * @return self
     */
    public function removeTimeLog(TimeLog $timeLog): self
    {
        $this->timeLogs->removeElement($timeLog);
        return $this;
    }

    /**
     * Get timeLogs
     *
     * @return Collection
     */
    public function getTimeLogs(): Collection
    {
        return $this->timeLogs;
    }

    /**
     * @return bool|null
     */
    public function isLocked(): ?bool
    {
        return $this->locked;
    }

    /**
     * @param bool|null $locked
     */
    public function setLocked(?bool $locked): void
    {
        $this->locked = $locked;
    }

    /**
     * @return bool|null
     */
    public function isFixe(): ?bool {
        return $this->fixe;
    }

    /**
     * @param bool|null $fixe
     */
    public function setFixe(?bool $fixe): void {
        $this->fixe = $fixe;
    }

    /**
     * Set position
     *
     * @param PeriodPosition|null $position
     *
     * @return Shift
     */
    public function setPosition(?PeriodPosition $position = null): self
    {
        $this->position = $position;

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
     * Return true if this is the first ever shift by the shifter
     *
     * @return bool
     */
    public function isFirstByShifter(): bool
    {
        if ($this->getShifter()) {
            // last? beneficiary->shifts are ordered by start DESC
            if ($this === $this->getShifter()->getShifts()->last()) {
                return true;
            }
        }
        return false;
    }
}

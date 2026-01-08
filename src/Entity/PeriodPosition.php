<?php

namespace App\Entity;

use App\Repository\PeriodPositionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'period_position')]
#[ORM\Entity(repositoryClass: PeriodPositionRepository::class)]
class PeriodPosition
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Formation::class)]
    #[ORM\JoinColumn(name: 'formation_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Formation $formation = null;

    #[ORM\ManyToOne(targetEntity: Period::class, inversedBy: 'positions')]
    #[ORM\JoinColumn(name: 'period_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Period $period = null;

    #[ORM\ManyToOne(targetEntity: Beneficiary::class)]
    #[ORM\JoinColumn(name: 'shifter_id', referencedColumnName: 'id')]
    private ?Beneficiary $shifter = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'booker_id', referencedColumnName: 'id')]
    private ?User $booker = null;

    #[ORM\Column(name: 'week_cycle', type: Types::STRING, length: 1, nullable: false)]
    private ?string $weekCycle = null;

    #[ORM\Column(name: 'booked_time', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $bookedTime = null;

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    public function __toString(): string
    {
        if ($this->getFormation()) {
            return (string) $this->getFormation()->getName();
        } else {
            return "Membre";
        }
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
     * Set period
     *
     * @param Period|null $period
     *
     * @return PeriodPosition
     */
    public function setPeriod(?Period $period = null): self
    {
        $this->period = $period;

        return $this;
    }

    /**
     * Get period
     *
     * @return Period|null
     */
    public function getPeriod(): ?Period
    {
        return $this->period;
    }

    /**
     * Set formation
     *
     * @param Formation|null $formation
     *
     * @return PeriodPosition
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
     * Set weekCycle
     *
     * @param string $weekCycle
     *
     * @return PeriodPosition
     */
    public function setWeekCycle(string $weekCycle): self
    {
        $this->weekCycle = $weekCycle;
        return $this;
    }

    /**
     * Get weekCycle
     *
     * @return string|null
     */
    public function getWeekCycle(): ?string
    {
        return $this->weekCycle;
    }

    /**
     * Set shifter
     *
     * @param Beneficiary|null $shifter
     *
     * @return PeriodPosition
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

    /**
     * Set booker
     *
     * @param User|null $booker
     *
     * @return PeriodPosition
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
     * Set bookedTime
     *
     * @param \DateTimeInterface|null $bookedTime
     *
     * @return PeriodPosition
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
     * free
     *
     * @return PeriodPosition
     */
    public function free(): self
    {
        $this->setBooker(null);
        $this->setBookedTime(null);
        $this->setShifter(null);
        return $this;
    }
}

<?php

namespace App\Entity;

use App\Repository\PeriodRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'period')]
#[ORM\Entity(repositoryClass: PeriodRepository::class)]
class Period
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'day_of_week', type: Types::SMALLINT)]
    private ?int $dayOfWeek = null;

    #[ORM\Column(name: 'start', type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $start = null;

    #[ORM\Column(name: 'end', type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $end = null;

    #[ORM\ManyToOne(targetEntity: Job::class, inversedBy: 'periods')]
    #[ORM\JoinColumn(name: 'job_id', referencedColumnName: 'id', nullable: false)]
    private ?Job $job = null;

    #[ORM\OneToMany(targetEntity: PeriodPosition::class, mappedBy: 'period', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $positions;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->positions = new ArrayCollection();
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
     * Set dayOfWeek
     *
     * @param int $dayOfWeek
     *
     * @return Period
     */
    public function setDayOfWeek(int $dayOfWeek): self
    {
        $this->dayOfWeek = $dayOfWeek;

        return $this;
    }

    /**
     * Get dayOfWeek
     *
     * @return int|null
     */
    public function getDayOfWeek(): ?int
    {
        return $this->dayOfWeek;
    }

    /**
     * Get dayOfWeekString
     *
     * @return string
     */
    public function getDayOfWeekString(): string
    {
        setlocale(LC_TIME, 'fr_FR.UTF8');
        return strftime("%A", strtotime("Monday + {$this->dayOfWeek} days"));
    }

    /**
     * Set start
     *
     * @param \DateTimeInterface|null $start
     *
     * @return Period
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
     * @return Period
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
     * Set job
     *
     * @param Job|null $job
     *
     * @return Period
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
     * Add periodPosition
     *
     * @param PeriodPosition $position
     *
     * @return Period
     */
    public function addPosition(PeriodPosition $position): self
    {
        $position->setPeriod($this);
        $this->positions[] = $position;

        return $this;
    }

    /**
     * Remove periodPosition
     *
     * @param PeriodPosition $position
     */
    public function removePosition(PeriodPosition $position): void
    {
        $this->positions->removeElement($position);
    }

    /**
     * Get periodPositions
     *
     * @return Collection
     */
    public function getPositions(): Collection
    {
        return $this->positions;
    }

    /**
     * Get all the positions per week cycle
     *
     * @return array
     */
    public function getPositionsPerWeekCycle(): array
    {
        $positionsPerWeekCycle = array();
        foreach ($this->positions as $position) {
            if (!array_key_exists($position->getWeekCycle(), $positionsPerWeekCycle)) {
                $positionsPerWeekCycle[$position->getWeekCycle()] = array();
            }
            $positionsPerWeekCycle[$position->getWeekCycle()][] = $position;
        }
        ksort($positionsPerWeekCycle);
        return $positionsPerWeekCycle;
    }

    /**
     * Return true if at least one shifter (a.k.a. beneficiary) registered for
     * this period is "problematic", meaning with a withdrawn or frozen membership
     * of if the shifter is member of the flying team.
     *
     * useful only if the use_fly_and_fixed is activated
     *
     * @param string|null $weekFilter a string of the week to keep or null if no filter
     * @return bool
     */
    public function isProblematic(?string $weekFilter = null): bool
    {
        foreach ($this->positions as $position) {
            if ($shifter = $position->getShifter()) {
                if ((($weekFilter && $position->getWeekCycle() == $weekFilter) or !$weekFilter)
                    and ($shifter->isFlying()
                    or $shifter->getMembership()->isFrozen()
                    or $shifter->getMembership()->isWithdrawn())) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Return true if no shifter (a.k.a. beneficiary) are registered for the period
     *
     * useful only if the use_fly_and_fixed is activated
     *
     * @param string|null $weekFilter a string of the week to keep or null if no filter
     * @return bool
     */
    public function isEmpty(?string $weekFilter = null): bool
    {
        // false at the first position with a shifter
        foreach ($this->positions as $position) {
            if ($position->getShifter()) {
                if (($weekFilter && $position->getWeekCycle() == $weekFilter) or !$weekFilter) {
                    return false;
                }
            }
        }
        // is empty if there are actually some position
        return count($this->getGroupedPositionsPerWeekCycle($weekFilter)) != 0;
    }

    /**
     * Return true if all the periods have been assigned to a shifter (a.k.a. beneficiary)
     *
     * useful only if the use_fly_and_fixed is activated
     *
     * @param string|null $weekFilter a string of the week to keep or null if no filter
     * @return bool
     */
    public function isFull(?string $weekFilter = null): bool
    {
        // false at the first position without a shifter
        foreach ($this->positions as $position) {
            if (!$position->getShifter()) {
                if (($weekFilter && $position->getWeekCycle() == $weekFilter) or !$weekFilter) {
                    return false;
                }
            }
        }
        // is empty if there are actually some position
        return count($this->getGroupedPositionsPerWeekCycle($weekFilter)) != 0;
    }

    /**
     * Return true if all the periods have been assigned to a shifter (a.k.a. beneficiary)
     *
     * useful only if the use_fly_and_fixed is activated
     *
     * @param string|null $weekFilter a string of the week to keep or null if no filter
     * @return bool
     */
    public function isPartial(?string $weekFilter = null): bool
    {
        // false at the first position with a shifter
        $slotEmpty = false;
        $slotTaken = false;

        foreach ($this->positions as $position) {
            if (($weekFilter && $position->getWeekCycle() == $weekFilter) or !$weekFilter) {
                if ($position->getShifter()) {
                    $slotTaken = true;
                } else {
                    $slotEmpty = true;
                }
            }
            if ($slotTaken and $slotEmpty) {
                return true;
            }
        }

        return false;
    }

    public function hasShifter(?Beneficiary $beneficiary = null)
    {
        if (!$beneficiary) {
            return true;
        }
        return $this->getPositions()->filter(function (PeriodPosition $position) use ($beneficiary) {
            return ($position->getShifter() === $beneficiary);
        });
    }

    /**
     * Get periodPositions grouped per week cycle
     *
     * @param string|null $weekFilter a string of the week to keep or null if no filter
     * @return array
     */
    public function getGroupedPositionsPerWeekCycle(?string $weekFilter = null): array
    {
        $aggregatePerFormation = array();
        foreach ($this->positions as $position) {
            if (!array_key_exists($position->getWeekCycle(), $aggregatePerFormation)) {
                $aggregatePerFormation[$position->getWeekCycle()] = array();
            }
            if ($position->getFormation()) {
                $formation = $position->getFormation()->getName();
            } else {
                $formation = "Membre";
            }
            if (array_key_exists($formation, $aggregatePerFormation[$position->getWeekCycle()])) {
                $aggregatePerFormation[$position->getWeekCycle()][$formation] += 1;
            } else {
                $aggregatePerFormation[$position->getWeekCycle()][$formation] = 1;
            }
        }
        ksort($aggregatePerFormation);
        $aggregatePerWeekCycle = array();


        foreach ($aggregatePerFormation as $week => $position) {
            if ($weekFilter && $week == $weekFilter or !$weekFilter) {
                //week_filter not null and in the filter list or week_filter null
                $key = $week;
                foreach ($aggregatePerWeekCycle as $w => $p) {
                    if ($p == $position) {
                        $key = $w . ", " . $week;
                        unset($aggregatePerWeekCycle[$w]);
                        break;
                    }
                }
                $aggregatePerWeekCycle[$key] = $position;
            }
        }

        ksort($aggregatePerWeekCycle);
        return $aggregatePerWeekCycle;
    }
}

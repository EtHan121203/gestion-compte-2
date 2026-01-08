<?php

namespace App\Entity;

use App\Entity\Shift;
use App\Entity\User;
use App\Entity\Beneficiary;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Shift Bucket
 * List of shift sharing the same start, end and job
 *
 * /!\ ATTENTION /!\
 * Le comportement implémenté repose sur l'hypothèse qu'il ne peut y avoir qu'un seul
 * rôle possible pour un bucket, donc pour un job. Il faudra modifier l'implémentation
 * si cela n'est plus le cas.
 */
class ShiftBucket
{

    /** @var Collection<int, Shift> */
    private Collection $shifts;

    public function __construct()
    {
        $this->shifts = new ArrayCollection();
    }

    public function addShift(Shift $shift): void
    {
        $this->shifts[] = $shift;
    }

    public function addShifts(iterable $shifts): void
    {
        foreach ($shifts as $shift) {
            if ($shift instanceof Shift) {
                $this->shifts[] = $shift;
            }
        }
    }

    public function removeEmptyShift(): void
    {
        foreach ($this->shifts as $shiftKey => $shift) {
            if ($shift->getShifter() == NULL && count($this->shifts) > 1) {
                unset($this->shifts[$shiftKey]);
            }
        }
    }

    public static function compareShifts(Shift $a, Shift $b, ?Beneficiary $beneficiary = null): int
    {
        if (!$beneficiary) {
            if (!$a->getFormation()) {
                if (!$b->getFormation()) {
                    if (!$a->getShifter()) {
                        if (!$b->getShifter()) {
                            return 0;
                        } else {
                            return 1;
                        }
                    } else {
                        if (!$b->getShifter()) {
                            return -1;
                        } else {
                            return $a->getBookedTime() <=> $b->getBookedTime();
                        }
                    }
                } else {
                    return 1;
                }
            } else {
                if (!$b->getFormation()) {
                    return -1;
                } else {
                    if ($a->getFormation()->getId() != $b->getFormation()->getId()) {
                        return $a->getFormation()->getId() <=> $b->getFormation()->getId();
                    } else {
                        return $a->getBookedTime() <=> $b->getBookedTime();
                    }
                }
            }
        }
        if ($a->getLastShifter() && $a->getLastShifter()->getId() == $beneficiary->getId()) {
            return -1;
        }
        if ($b->getLastShifter() && $b->getLastShifter()->getId() == $beneficiary->getId()) {
            return 1;
        }
        return 0;
    }

    /** @return Collection<int, Shift> */
    public function getShifts(): Collection
    {
        return $this->shifts;
    }

    /** @return int[] */
    public function getShiftIds(): array
    {
        $ids = array();
        foreach ($this->getShifts() as $shift){
            $ids[] = $shift->getId();
        }
        return $ids;
    }

    public function getId(): ?int
    {
        $ids = $this->getShiftIds();
        return empty($ids) ? null : min($ids);
    }

    public function getShiftWithMinId(): ?Shift
    {
        $min = $this->shifts->first();
        if (!$min) return null;

        foreach ($this->getShifts() as $shift){
            if ($min->getId() > $shift->getId()) {
                $min = $shift;
            }
        }
        return $min;
    }

    public function getShifterCount(): int
    {
        $bookedShifts = $this->getShifts()->filter(function (Shift $shift) {
            return ($shift->getShifter() != NULL);
        });
        return count($bookedShifts);
    }

    public function getSortedShifts(): ?Collection
    {
        $iterator = $this->getShifts()->getIterator();
        $iterator->uasort(function (Shift $a, Shift $b) {
            return self::compareShifts($a, $b);
        });
        $sorted = new ArrayCollection(iterator_to_array($iterator));
        return $sorted->isEmpty() ? null : $sorted;

    }

    public function getFirst(): ?Shift
    {
        return $this->shifts->first() ?: null;
    }

    /**
     * @return Job|null
     */
    public function getJob(): ?Job
    {
        $first = $this->getFirst();
        return $first ? $first->getJob() : null;
    }

    public function getStart(): ?\DateTimeInterface
    {
        $first = $this->getFirst();
        return $first ? $first->getStart() : null;
    }

    public function getEnd(): ?\DateTimeInterface
    {
        $first = $this->getFirst();
        return $first ? $first->getEnd() : null;
    }

    public function getDuration(): ?float
    {
        $first = $this->getFirst();
        return $first ? $first->getDuration() : null;
    }

    /**
     * - check that none of the shifts belong to the beneficiary
     * - check that the beneficiary doesn't already have a shift in the same interval
     */
    public function canBookInterval(Beneficiary $beneficiary): bool
    {
        $alreadyBooked = $beneficiary->getShifts()->exists(function ($key, Shift $shift) {
            return $shift->getStart() == $this->getStart() && $shift->getEnd() == $this->getEnd();
        });
        $alreadyReserved = $beneficiary->getReservedShifts()->exists(function ($key, Shift $shift) {
            return $shift->getStart() == $this->getStart() && $shift->getEnd() == $this->getEnd();
        });
        return !$alreadyBooked && !$alreadyReserved;
    }

    public function getIntervalCode(): ?string
    {
        return $this->shifts->isEmpty() ? null : $this->shifts[0]->getIntervalCode();
    }

    /**
     * Return true if the intersection between $formations and
     * the shifts' formations is not empty.
     */
    public static function shiftIntersectFormations(Collection $shifts, iterable $formations): bool
    {
        $formationsIds = [];
        foreach ($formations as $formation) {
            $formationsIds[] = $formation->getId();
        }

        $formationInFormationIdsCallback = function ($key, Shift $shift) use ($formationsIds) {
            $formation = $shift->getFormation();
            return !$formation ? false : in_array($formation->getId(), $formationsIds);
        };

        return $shifts->exists($formationInFormationIdsCallback);
    }

    /**
     * Renvoie une collection filtrée en fonction des formations.
     *
     * Si un des shifts a une formation qui appartient à $formations,
     * on renvoie seulement les shifts qui ont un formation.
     *
     * Sinon, on renvoie seulement les shifts qui n'ont pas de formation.
     */
    public static function filterByFormations(Collection $shifts, iterable $formations): Collection
    {
        $intersectionNotEmpty = self::shiftIntersectFormations($shifts, $formations);
        $filterCallback = self::createShiftFilterCallback($intersectionNotEmpty);
        return $shifts->filter($filterCallback);
    }

    /**
     * If $withFormations, return a callback which returns true if the
     * shift has a formation.
     *
     * Else, return a callback which return true if the shift
     * doesn't have a formation.
     */
    public static function createShiftFilterCallback(bool $withFormations): \Closure
    {
        if ($withFormations) {
            return function (Shift $shift) {
                return $shift->getFormation() !== null;
            };
        } else {
            return function (Shift $shift) {
                return $shift->getFormation() === null;
            };
        }
    }
}

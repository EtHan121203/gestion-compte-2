<?php

namespace App\Entity;

use App\Repository\MembershipRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'membership')]
#[ORM\UniqueConstraint(columns: ['member_number'])]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: MembershipRepository::class)]
#[UniqueEntity(fields: ['member_number'], message: 'Ce numéro de membre existe déjà')]
class Membership
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    protected ?int $id = null;

    #[ORM\Column(type: 'bigint')]
    #[Assert\NotBlank(message: "Merci d'entrer votre numéro d'adhérent")]
    protected ?string $member_number = null;

    #[ORM\Column(name: 'withdrawn', type: 'boolean', options: ['default' => 0])]
    private bool $withdrawn = false;

    #[ORM\Column(name: 'withdrawn_date', type: 'date', nullable: true)]
    private ?\DateTimeInterface $withdrawnDate = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'withdrawn_by_id', referencedColumnName: 'id')]
    private ?User $withdrawnBy = null;

    #[ORM\Column(name: 'frozen', type: 'boolean', options: ['default' => 0])]
    private bool $frozen = false;

    #[ORM\Column(name: 'frozen_change', type: 'boolean', options: ['default' => 0])]
    private bool $frozen_change = false;

    /**
     * @var Collection<int, Registration>
     */
    #[ORM\OneToMany(targetEntity: Registration::class, mappedBy: 'membership', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['date' => 'DESC'])]
    private Collection $registrations;

    /**
     * @var Collection<int, Beneficiary>
     */
    #[ORM\OneToMany(targetEntity: Beneficiary::class, mappedBy: 'membership', cascade: ['persist', 'remove'])]
    private Collection $beneficiaries;

    #[ORM\OneToOne(targetEntity: Beneficiary::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'main_beneficiary_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    #[Assert\Valid]
    private ?Beneficiary $mainBeneficiary = null;

    /**
     * @var Collection<int, Note>
     */
    #[ORM\OneToMany(targetEntity: Note::class, mappedBy: 'subject', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $notes;

    /**
     * @var Collection<int, Proxy>
     */
    #[ORM\OneToMany(targetEntity: Proxy::class, mappedBy: 'giver', cascade: ['persist', 'remove'])]
    private Collection $given_proxies;

    #[ORM\Column(name: 'first_shift_date', type: 'date', nullable: true)]
    private ?\DateTimeInterface $firstShiftDate = null;

    /**
     * @var Collection<int, TimeLog>
     */
    #[ORM\OneToMany(targetEntity: TimeLog::class, mappedBy: 'membership', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['createdAt' => 'DESC', 'type' => 'DESC'])]
    private Collection $timeLogs;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    /**
     * @var Collection<int, MembershipShiftExemption>
     */
    #[ORM\OneToMany(targetEntity: MembershipShiftExemption::class, mappedBy: 'membership', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $membershipShiftExemptions;

    public function __construct()
    {
        $this->registrations = new ArrayCollection();
        $this->beneficiaries = new ArrayCollection();
        $this->timeLogs = new ArrayCollection();
        $this->notes = new ArrayCollection();
        $this->given_proxies = new ArrayCollection();
        $this->membershipShiftExemptions = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getDisplayMemberNumber();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    public function getTmpToken(string $key = ''): string
    {
        return md5((string)$this->getId() . (string)$this->getMemberNumber() . $key . date('d'));
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
     * Set memberNumber
     *
     * @param string|int|null $memberNumber
     *
     * @return self
     */
    public function setMemberNumber($memberNumber): self
    {
        $this->member_number = (string)$memberNumber;
        return $this;
    }

    /**
     * Get memberNumber
     *
     * @return string|null
     */
    public function getMemberNumber(): ?string
    {
        return $this->member_number;
    }

    /**
     * Add registration
     *
     * @param Registration $registration
     *
     * @return self
     */
    public function addRegistration(Registration $registration): self
    {
        if (!$this->registrations->contains($registration)) {
            $this->registrations[] = $registration;
            $registration->setMembership($this);
        }
        return $this;
    }

    /**
     * Remove registration
     *
     * @param Registration $registration
     * @return self
     */
    public function removeRegistration(Registration $registration): self
    {
        if ($this->registrations->removeElement($registration)) {
            // set the owning side to null (unless already changed)
            if ($registration->getMembership() === $this) {
                $registration->setMembership(null);
            }
        }
        return $this;
    }

    /**
     * Get registrations
     *
     * @return Collection<int, Registration>
     */
    public function getRegistrations(): Collection
    {
        return $this->registrations;
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
            $beneficiary->setMembership($this);
        }
        return $this;
    }

    /**
     * Remove beneficiary
     *
     * @param Beneficiary $beneficiary
     * @return self
     */
    public function removeBeneficiary(Beneficiary $beneficiary): self
    {
        if ($this->beneficiaries->removeElement($beneficiary)) {
            // set the owning side to null (unless already changed)
            if ($beneficiary->getMembership() === $this) {
                $beneficiary->setMembership(null);
            }
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
     * Get beneficiaries (with main in first position)
     *
     * @return array<int, Beneficiary>
     */
    public function getBeneficiariesWithMainInFirstPosition(): array
    {
        $beneficiaries = [];
        if ($this->getMainBeneficiary()) {
            $beneficiaries[] = $this->getMainBeneficiary();
        }
        
        foreach ($this->getBeneficiaries() as $beneficiary) {
            if ($beneficiary !== $this->getMainBeneficiary()) {
                $beneficiaries[] = $beneficiary;
            }
        }
        
        return $beneficiaries;
    }

    /**
     * Get member_number & list of beneficiaries
     *
     * @return string
     */
    public function getMemberNumberWithBeneficiaryListString(): string
    {
        $memberNumberWithBeneficiaryListString = '#' . $this->getMemberNumber();
        foreach ($this->getBeneficiariesWithMainInFirstPosition() as $key => $beneficiary) {
            if ($key > 0) {
                $memberNumberWithBeneficiaryListString .= ' &';
            }
            $memberNumberWithBeneficiaryListString .= ' '. $beneficiary->getDisplayName();
        }
        return $memberNumberWithBeneficiaryListString;
    }

    /**
     * Set mainBeneficiary
     *
     * @param Beneficiary|null $mainBeneficiary
     *
     * @return self
     */
    public function setMainBeneficiary(?Beneficiary $mainBeneficiary = null): self
    {
        if ($mainBeneficiary) {
            $this->addBeneficiary($mainBeneficiary);
        }

        $this->mainBeneficiary = $mainBeneficiary;

        return $this;
    }

    /**
     * Get mainBeneficiary
     *
     * @return Beneficiary|null
     */
    public function getMainBeneficiary(): ?Beneficiary
    {
        if (!$this->mainBeneficiary){
            if ($this->getBeneficiaries()->count()) {
                $this->setMainBeneficiary($this->getBeneficiaries()->first());
            }
        }
        return $this->mainBeneficiary;
    }

    /**
     * Set withdrawn
     *
     * @param bool $withdrawn
     *
     * @return self
     */
    public function setWithdrawn(bool $withdrawn): self
    {
        $this->withdrawn = $withdrawn;
        if ($this->withdrawn === false) {
            $this->withdrawnDate = null;
            $this->withdrawnBy = null;
        }
        return $this;
    }

    /**
     * Get isWithdrawn
     *
     * @return bool
     */
    public function isWithdrawn(): bool
    {
        return $this->withdrawn;
    }

    /**
     * Get withdrawn
     *
     * @return bool
     */
    public function getWithdrawn(): bool
    {
        return $this->withdrawn;
    }

    /**
     * Set withdrawnDate
     *
     * @param \DateTimeInterface|null $date
     *
     * @return self
     */
    public function setWithdrawnDate(?\DateTimeInterface $date): self
    {
        $this->withdrawnDate = $date;
        return $this;
    }

    /**
     * Get withdrawnDate
     *
     * @return \DateTimeInterface|null
     */
    public function getWithdrawnDate(): ?\DateTimeInterface
    {
        return $this->withdrawnDate;
    }

    /**
     * Set withdrawnBy
     *
     * @param User|null $user
     *
     * @return self
     */
    public function setWithdrawnBy(?User $user = null): self
    {
        $this->withdrawnBy = $user;
        return $this;
    }

    /**
     * Get withdrawnBy
     *
     * @return User|null
     */
    public function getWithdrawnBy(): ?User
    {
        return $this->withdrawnBy;
    }

    public function getCommissions(): Collection
    {
        $commissions = [];
        foreach ($this->getBeneficiaries() as $beneficiary){
            $commissions = array_merge($beneficiary->getCommissions()->toArray(), $commissions);
        }
        return new ArrayCollection(array_unique($commissions, SORT_REGULAR));
    }

    public function getOwnedCommissions(): Collection
    {
        return $this->getCommissions()->filter(function($commission) {
            foreach ($commission->getOwners() as $owner){
                if ($this->getBeneficiaries()->contains($owner)) {
                    return true;
                }
            }
            return false;
        });
    }

    /**
     * Set frozen
     *
     * @param bool $frozen
     *
     * @return self
     */
    public function setFrozen(bool $frozen): self
    {
        $this->frozen = $frozen;

        return $this;
    }

    /**
     * Get frozen
     *
     * @return bool
     */
    public function getFrozen(): bool
    {
        return $this->frozen;
    }

    /**
     * return if the member is frozen
     *
     * @return bool
     */
    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    /**
     * Set frozen_change
     *
     * @param bool $frozen_change
     *
     * @return self
     */
    public function setFrozenChange(bool $frozen_change): self
    {
        $this->frozen_change = $frozen_change;
        return $this;
    }

    /**
     * Get frozen_change
     *
     * @return bool
     */
    public function getFrozenChange(): bool
    {
        return $this->frozen_change;
    }

    /**
     * Get lastRegistration
     *
     * @return Registration|null
     */
    public function getLastRegistration(): ?Registration
    {
        return $this->getRegistrations()->first() ?: null;
    }

    /**
     * Return if the member has a valid registration before the given date
     *
     * @param \DateTimeInterface|null $date
     * @return bool
     */
    public function hasValidRegistrationBefore(?\DateTimeInterface $date): bool
    {
        if (!$date) {
            $date = new \DateTime('now');
        }
        foreach ($this->getRegistrations() as $registration) {
            if ($registration->getDate() < $date) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all reserved shifts for all beneficiaries
     * @return Collection<int, Shift>
     */
    public function getReservedShifts(): Collection
    {
        $shifts = new ArrayCollection();
        foreach ($this->getBeneficiaries() as $beneficiary) {
            foreach ($beneficiary->getReservedShifts() as $shift) {
                if (!$shifts->contains($shift)) {
                    $shifts->add($shift);
                }
            }
        }
        return $shifts;
    }

    /**
     * Add note
     *
     * @param Note $note
     *
     * @return self
     */
    public function addNote(Note $note): self
    {
        if (!$this->notes->contains($note)) {
            $this->notes[] = $note;
            $note->setSubject($this);
        }
        return $this;
    }

    /**
     * Remove note
     *
     * @param Note $note
     * @return self
     */
    public function removeNote(Note $note): self
    {
        if ($this->notes->removeElement($note)) {
            // set the owning side to null (unless already changed)
            if ($note->getSubject() === $this) {
                $note->setSubject(null);
            }
        }
        return $this;
    }

    /**
     * Get notes
     *
     * @return Collection<int, Note>
     */
    public function getNotes(): Collection
    {
        return $this->notes;
    }

    /**
     * Add givenProxy
     *
     * @param Proxy $givenProxy
     *
     * @return self
     */
    public function addGivenProxy(Proxy $givenProxy): self
    {
        if (!$this->given_proxies->contains($givenProxy)) {
            $this->given_proxies[] = $givenProxy;
            $givenProxy->setGiver($this);
        }
        return $this;
    }

    /**
     * Remove givenProxy
     *
     * @param Proxy $givenProxy
     * @return self
     */
    public function removeGivenProxy(Proxy $givenProxy): self
    {
        if ($this->given_proxies->removeElement($givenProxy)) {
            // set the owning side to null (unless already changed)
            if ($givenProxy->getGiver() === $this) {
                $givenProxy->setGiver(null);
            }
        }
        return $this;
    }

    /**
     * Get givenProxies
     *
     * @return Collection<int, Proxy>
     */
    public function getGivenProxies(): Collection
    {
        return $this->given_proxies;
    }

    public function getDisplayMemberNumber(): string
    {
        return '#' . $this->getMemberNumber();
    }

    /**
     * Set firstShiftDate
     *
     * @param \DateTimeInterface|null $firstShiftDate
     *
     * @return self
     */
    public function setFirstShiftDate(?\DateTimeInterface $firstShiftDate): self
    {
        $this->firstShiftDate = $firstShiftDate;
        return $this;
    }

    /**
     * Get firstShiftDate
     *
     * @return \DateTimeInterface|null
     */
    public function getFirstShiftDate(): ?\DateTimeInterface
    {
        return $this->firstShiftDate;
    }

    /**
     * Add timeLog
     *
     * @param TimeLog $timeLog
     *
     * @return self
     */
    public function addTimeLog(TimeLog $timeLog): self
    {
        if (!$this->timeLogs->contains($timeLog)) {
            $this->timeLogs[] = $timeLog;
            $timeLog->setMembership($this);
        }
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
        if ($this->timeLogs->removeElement($timeLog)) {
            // set the owning side to null (unless already changed)
            if ($timeLog->getMembership() === $this) {
                $timeLog->setMembership(null);
            }
        }
        return $this;
    }

    /**
     * Get timeLogs
     *
     * @return Collection<int, TimeLog>
     */
    public function getTimeLogs(): Collection
    {
        return $this->timeLogs;
    }

    /**
     * Get shiftTimeLogs
     *
     * @return Collection<int, TimeLog>
     */
    public function getShiftTimeLogs(): Collection
    {
        return $this->timeLogs->filter(function (TimeLog $log) {
            return ($log->getType() != TimeLog::TYPE_SAVING);
        });
    }

    /**
     * Get savingTimeLogs
     *
     * @return Collection<int, TimeLog>
     */
    public function getSavingTimeLogs(): Collection
    {
        return $this->timeLogs->filter(function (TimeLog $log) {
            return ($log->getType() == TimeLog::TYPE_SAVING);
        });
    }

    public function getShiftTimeCount(?\DateTimeInterface $before = null): float
    {
        $sum = function($carry, TimeLog $log)
        {
            $carry += $log->getTime();
            return $carry;
        };

        $logs = $this->getShiftTimeLogs();
        if ($before) {
            $logs = $logs->filter(function (TimeLog $log) use ($before) {
                return ($log->getCreatedAt() < $before);
            });
        }

        return (float)array_reduce($logs->toArray(), $sum, 0);
    }

    public function getSavingTimeCount(?\DateTimeInterface $before = null): float
    {
        $sum = function($carry, TimeLog $log)
        {
            $carry += $log->getTime();
            return $carry;
        };

        $logs = $this->getSavingTimeLogs();
        if ($before) {
            $logs = $logs->filter(function (TimeLog $log) use ($before) {
                return ($log->getCreatedAt() < $before);
            });
        }

        return (float)array_reduce($logs->toArray(), $sum, 0);
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
     * Get membershipShiftExemptions
     *
     * @return Collection<int, MembershipShiftExemption>
     */
    public function getMembershipShiftExemptions(): Collection
    {
        return $this->membershipShiftExemptions;
    }

    /**
     * Get valid membership shiftExemptions
     *
     * @return Collection<int, MembershipShiftExemption>
     */
    public function getCurrentMembershipShiftExemptions(?\DateTimeInterface $date = null): Collection
    {
        if (!$date) {
            $date = new \DateTime('now');
        }
        return $this->membershipShiftExemptions->filter(function($membershipShiftExemption) use ($date) {
            return $membershipShiftExemption->isCurrent($date);
        });
    }

    /**
     * Return if the membership is exempted from doing shifts
     *
     * @param \DateTimeInterface|null $date
     * @return bool
     */
    public function isCurrentlyExemptedFromShifts(?\DateTimeInterface $date = null): bool
    {
        if (!$date) {
            $date = new \DateTime('now');
        }
        return $this->membershipShiftExemptions->exists(function($key, $membershipShiftExemption) use ($date) {
            return $membershipShiftExemption->isCurrent($date);
        });
    }
}

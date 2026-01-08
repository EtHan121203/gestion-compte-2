<?php

namespace App\Entity;

use App\Repository\TimeLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'time_log')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: TimeLogRepository::class)]
class TimeLog
{
    public const TYPE_CUSTOM = 0;

    public const TYPE_SHIFT_VALIDATED = 1;
    public const TYPE_SHIFT_INVALIDATED = 10;
    public const TYPE_SHIFT_FREED_SAVING = 21;

    public const TYPE_CYCLE_END = 2;
    public const TYPE_CYCLE_END_FROZEN = 3;
    public const TYPE_CYCLE_END_EXPIRED_REGISTRATION = 4;
    public const TYPE_CYCLE_END_EXEMPTED = 6;

    public const TYPE_REGULATE_OPTIONAL_SHIFTS = 5;

    public const TYPE_SAVING = 20;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id')]
    private ?User $createdBy = null;

    #[ORM\Column(name: 'time', type: 'smallint')]
    private ?int $time = null;

    #[ORM\Column(name: 'type', type: 'smallint')]
    private ?int $type = null;

    #[ORM\Column(name: 'description', type: 'string', length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: Membership::class, inversedBy: 'timeLogs')]
    #[ORM\JoinColumn(name: 'membership_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    private ?Membership $membership = null;

    #[ORM\ManyToOne(targetEntity: Shift::class, inversedBy: 'timeLogs')]
    #[ORM\JoinColumn(name: 'shift_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Shift $shift = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $requestRoute = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTime();
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
     * Get createdAt
     *
     * @return \DateTimeInterface|null
     */
    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Set createdBy
     *
     * @param User|null $user
     *
     * @return TimeLog
     */
    public function setCreatedBy(?User $user = null): self
    {
        $this->createdBy = $user;

        return $this;
    }

    /**
     * Get createdBy
     *
     * @return User|null
     */
    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    /**
     * Set time
     *
     * @param int|null $time
     *
     * @return TimeLog
     */
    public function setTime(?int $time): self
    {
        $this->time = $time;

        return $this;
    }

    /**
     * Get time
     *
     * @return int|null
     */
    public function getTime(): ?int
    {
        return $this->time;
    }

    /**
     * Set description
     *
     * @param string|null $description
     *
     * @return TimeLog
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
     * Set shift
     *
     * @param Shift|null $shift
     *
     * @return TimeLog
     */
    public function setShift(?Shift $shift = null): self
    {
        $this->shift = $shift;

        return $this;
    }

    /**
     * Get shift
     *
     * @return Shift|null
     */
    public function getShift(): ?Shift
    {
        return $this->shift;
    }

    /**
     * @return Membership|null
     */
    public function getMembership(): ?Membership
    {
        return $this->membership;
    }

    /**
     * @param Membership|null $membership
     */
    public function setMembership(?Membership $membership): void
    {
        $this->membership = $membership;
    }

    /**
     * @return int|null
     */
    public function getType(): ?int
    {
        return $this->type;
    }

    /**
     * Set created_at
     *
     * @param \DateTimeInterface|null $date
     *
     * @return TimeLog
     */
    public function setCreatedAt(?\DateTimeInterface $date): self
    {
        $this->createdAt = $date;

        return $this;
    }

    /**
     * @param int $type
     */
    public function setType(int $type): void
    {
        $this->type = $type;
    }

    public function getRequestRoute(): ?string
    {
        return $this->requestRoute;
    }

    public function setRequestRoute(?string $requestRoute): self
    {
        $this->requestRoute = $requestRoute;

        return $this;
    }

    /**
     * @return string
     */
    public function getTypeDisplay(): string
    {
        switch ($this->type) {
            case self::TYPE_CUSTOM:
                return (string) $this->description;
            case self::TYPE_SHIFT_VALIDATED:
                return "Créneau validé";
            case self::TYPE_SHIFT_INVALIDATED:
                return "Créneau invalidé";
            case self::TYPE_SHIFT_FREED_SAVING:
                return "Créneau libéré et compteur temps incrémenté (grâce au compteur épargne)";
            case self::TYPE_CYCLE_END:
                return "Début de cycle";
            case self::TYPE_CYCLE_END_FROZEN:
                return "Début de cycle (compte gelé)";
            case self::TYPE_CYCLE_END_EXPIRED_REGISTRATION:
                return "Début de cycle (compte expiré)";
            case self::TYPE_CYCLE_END_EXEMPTED:
                return "Début de cycle (compte exempté de créneau - exemption n°" . implode(",", $this->membership?->getMembershipShiftExemptions()->filter(function($membershipShiftExemption) {
                    return $membershipShiftExemption->isCurrent($this->createdAt);
                })->map(function($element) {
                    return $element->getId();
                })->toArray() ?? []) . ")";
            case self::TYPE_REGULATE_OPTIONAL_SHIFTS:
                return "Régulation du bénévolat facultatif";
            case self::TYPE_SAVING:
                if ($this->getTime() >= 0) {
                    return "Compteur épargne incrémenté";
                } else {
                    return "Compteur épargne décrémenté";
                }
        }
        return "Type de log de temps inconnu : " . $this->type;
    }
}

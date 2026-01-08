<?php

namespace App\Entity;

use App\Repository\BeneficiaryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'beneficiary')]
#[ORM\Entity(repositoryClass: BeneficiaryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Beneficiary
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom du bénéficiaire est requis')]
    private ?string $lastname = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le prénom du bénéficiaire est requis')]
    private ?string $firstname = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phone = null;

    #[ORM\OneToOne(inversedBy: 'beneficiary', targetEntity: Address::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'address_id', referencedColumnName: 'id')]
    #[Assert\NotNull]
    #[Assert\Valid]
    private ?Address $address = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 0], nullable: false)]
    private ?bool $flying = null;

    #[ORM\OneToOne(inversedBy: 'beneficiary', targetEntity: User::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull]
    #[Assert\Valid]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'beneficiaries', targetEntity: Membership::class)]
    #[ORM\JoinColumn(name: 'membership_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Membership $membership = null;

    #[ORM\OneToMany(mappedBy: 'shifter', targetEntity: Shift::class, cascade: ['remove'])]
    #[ORM\OrderBy(['start' => 'DESC'])]
    private Collection $shifts;

    #[ORM\OneToMany(mappedBy: 'lastShifter', targetEntity: Shift::class, cascade: ['remove'])]
    private Collection $reservedShifts;

    #[ORM\OneToMany(mappedBy: 'beneficiary', targetEntity: SwipeCard::class, cascade: ['remove'])]
    #[ORM\OrderBy(['number' => 'DESC'])]
    private Collection $swipe_cards;

    #[ORM\ManyToOne(inversedBy: 'owners', targetEntity: Commission::class)]
    #[ORM\JoinColumn(name: 'commission_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Commission $own = null;

    #[ORM\ManyToMany(inversedBy: 'beneficiaries', targetEntity: Commission::class)]
    #[ORM\JoinTable(name: 'beneficiaries_commissions')]
    private Collection $commissions;

    #[ORM\ManyToMany(mappedBy: 'owners', targetEntity: Task::class)]
    private Collection $tasks;

    #[ORM\ManyToMany(inversedBy: 'beneficiaries', targetEntity: Formation::class)]
    #[ORM\JoinTable(name: 'beneficiaries_formations')]
    private Collection $formations;

    #[ORM\OneToMany(mappedBy: 'owner', targetEntity: Proxy::class, cascade: ['persist', 'remove'])]
    private Collection $received_proxies;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->commissions = new ArrayCollection();
        $this->formations = new ArrayCollection();
        $this->shifts = new ArrayCollection();
        $this->reservedShifts = new ArrayCollection();
        $this->swipe_cards = new ArrayCollection();
        $this->tasks = new ArrayCollection();
        $this->received_proxies = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->getDisplayNameWithMemberNumber();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue()
    {
        $this->createdAt = new \DateTime();
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get membernumber
     *
     * @return int
     */
    public function getMemberNumber()
    {
        return $this->getMembership()->getMemberNumber();
    }

    /**
     * Get firstname
     *
     * @return string
     */
    public function getFirstname()
    {
        return ucfirst(strtolower($this->firstname));
    }

    /**
     * Set firstname
     *
     * @param string $firstname
     *
     * @return Beneficiary
     */
    public function setFirstname($firstname)
    {
        $this->firstname = $firstname;

        return $this;
    }

    /**
     * Set lastname
     *
     * @param string $lastname
     *
     * @return Beneficiary
     */
    public function setLastname($lastname)
    {
        $this->lastname = $lastname;

        return $this;
    }

    /**
     * Get lastname
     *
     * @return string
     */
    public function getLastname()
    {
        return strtoupper($this->lastname);
    }

    public function getDisplayName(): string
    {
        return $this->getFirstname() . ' ' . $this->getLastname();
    }

    /**
     * /!\ DO NOT MODIFY /!\
     *
     * Such a method is also used for autocomplete. If you want to
     * change it, you HAVE to adapt the methods used in data
     * transformer: BeneficiaryToStringTransformer. Otherwise,
     * autocomplete will be broken.
     */
    public function getDisplayNameWithMemberNumber(): string
    {
        return '#' . $this->getMemberNumber() . ' ' . $this->getFirstname() . ' ' . $this->getLastname();
    }

    public function getPublicDisplayName(): string
    {
        return $this->getFirstname() . ' ' . $this->getLastname()[0];
    }

    public function getPublicDisplayNameWithMemberNumber(): string
    {
        return '#' . $this->getMemberNumber() . ' ' . $this->getPublicDisplayName();
    }

    /**
     * Set email
     *
     * @param string $email
     *
     * @return Beneficiary
     */
    public function setEmail($email)
    {
        $this->getUser()->setEmail($email);

        return $this;
    }

    /**
     * Get email
     *
     * @return string
     */
    public function getEmail()
    {
        if ($this->getUser()) {
            return $this->getUser()->getEmail();
        } else {
            return null;
        }
    }

    /**
     * Set phone
     *
     * @param string $phone
     *
     * @return Beneficiary
     */
    public function setPhone($phone)
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * Get phone
     *
     * @return string
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * Set user
     *
     * @param User|null $user
     *
     * @return Beneficiary
     */
    public function setUser(User $user = null)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return User|null
     */
    public function getUser()
    {
        return $this->user;
    }

    public function isMain()
    {
        return $this === $this->getMembership()->getMainBeneficiary();
    }

    /**
     * Add commission
     *
     * @param Commission $commission
     *
     * @return Beneficiary
     */
    public function addCommission(Commission $commission)
    {
        $this->commissions[] = $commission;

        return $this;
    }

    /**
     * Remove commission
     *
     * @param Commission $commission
     */
    public function removeCommission(Commission $commission)
    {
        $this->commissions->removeElement($commission);
    }

    /**
     * Get commissions
     *
     * @return Collection
     */
    public function getCommissions()
    {
        return $this->commissions;
    }

    public function getOwnedCommissions()
    {
        return $this->commissions->filter(function ($commission) {
            return $commission->getOwners()->contains($this);
        });
    }

    /**
     * Add formation
     *
     * @param Formation $formation
     *
     * @return Beneficiary
     */
    public function addFormation(Formation $formation)
    {
        $this->formations[] = $formation;

        return $this;
    }

    /**
     * Remove formation
     *
     * @param Formation $formation
     */
    public function removeFormation(Formation $formation)
    {
        $this->formations->removeElement($formation);
    }

    /**
     * Get formations
     *
     * @return Collection
     */
    public function getFormations()
    {
        return $this->formations;
    }

    /**
     * Set own
     *
     * @param Commission|null $own
     *
     * @return Beneficiary
     */
    public function setOwn(Commission $own = null)
    {
        $this->own = $own;

        return $this;
    }

    /**
     * Get own
     *
     * @return Commission|null
     */
    public function getOwn()
    {
        return $this->own;
    }

    /**
     * Add shift
     *
     * @param Shift $shift
     *
     * @return Beneficiary
     */
    public function addShift(Shift $shift)
    {
        $this->shifts[] = $shift;

        return $this;
    }

    /**
     * Remove shift
     *
     * @param Shift $shift
     */
    public function removeShift(Shift $shift)
    {
        $this->shifts->removeElement($shift);
    }

    /**
     * Get shifts
     *
     * @return Collection
     */
    public function getShifts()
    {
        return $this->shifts;
    }

    /**
     * Add task
     *
     * @param Task $task
     *
     * @return Beneficiary
     */
    public function addTask(Task $task)
    {
        $this->tasks[] = $task;

        return $this;
    }

    /**
     * Remove task
     *
     * @param Task $task
     */
    public function removeTask(Task $task)
    {
        $this->tasks->removeElement($task);
    }

    /**
     * Get tasks
     *
     * @return Collection
     */
    public function getTasks()
    {
        return $this->tasks;
    }

    /**
     * Add receivedProxy
     *
     * @param Proxy $receivedProxy
     *
     * @return Beneficiary
     */
    public function addReceivedProxy(Proxy $receivedProxy)
    {
        $this->received_proxies[] = $receivedProxy;

        return $this;
    }

    /**
     * Remove receivedProxy
     *
     * @param Proxy $receivedProxy
     */
    public function removeReceivedProxy(Proxy $receivedProxy)
    {
        $this->received_proxies->removeElement($receivedProxy);
    }

    /**
     * Get receivedProxies
     *
     * @return Collection
     */
    public function getReceivedProxies()
    {
        return $this->received_proxies;
    }

    /**
     * Add reservedShift
     *
     * @param Shift $reservedShift
     *
     * @return Beneficiary
     */
    public function addReservedShift(Shift $reservedShift)
    {
        $this->reservedShifts[] = $reservedShift;

        return $this;
    }

    /**
     * Remove reservedShift
     *
     * @param Shift $reservedShift
     */
    public function removeReservedShift(Shift $reservedShift)
    {
        $this->reservedShifts->removeElement($reservedShift);
    }

    /**
     * Get reservedShifts
     *
     * @return Collection
     */
    public function getReservedShifts()
    {
        return $this->reservedShifts;
    }

    /**
     * Add swipeCard
     *
     * @param SwipeCard $swipeCard
     *
     * @return Beneficiary
     */
    public function addSwipeCard(SwipeCard $swipeCard)
    {
        $this->swipe_cards[] = $swipeCard;

        return $this;
    }

    /**
     * Remove swipeCard
     *
     * @param SwipeCard $swipeCard
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeSwipeCard(SwipeCard $swipeCard)
    {
        return $this->swipe_cards->removeElement($swipeCard);
    }

    /**
     * Get swipeCards
     *
     * @return Collection
     */
    public function getSwipeCards()
    {
        return $this->swipe_cards;
    }

    /**
     * Get enabled swipeCards
     *
     * @return Collection
     */
    public function getEnabledSwipeCards()
    {
        return $this->swipe_cards->filter(function ($card) {
            return $card->getEnable();
        });
    }

    /**
     * @return Membership|null
     */
    public function getMembership()
    {
        return $this->membership;
    }

    /**
     * @param mixed $membership
     */
    public function setMembership($membership)
    {
        $this->membership = $membership;
    }

    /**
     * @return Address|null
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @param mixed $address
     */
    public function setAddress($address)
    {
        $this->address = $address;
    }

    /**
     * @return bool
     */
    public function isFlying(): ?bool {
        return $this->flying;
    }

    /**
     * @param bool $flying
     */
    public function setFlying(?bool $flying): void {
        $this->flying = $flying;
    }

    /**
     * Get createdAt
     *
     * @return \DateTimeInterface|null
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Simple method to detect new beneficiaires.
     * TODO: move to Membership? Look at registration data instead?
     * 
     * @return bool
     */
    public function isNew()
    {
        $shiftCountThreshold = 3;

        return $this->shifts->count() <= $shiftCountThreshold;
    }
}

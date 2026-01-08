<?php

namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Table(name: 'event')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: EventRepository::class)]
#[Vich\Uploadable]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'title', type: 'string', length: 255)]
    #[Assert\NotBlank]
    private ?string $title = null;

    #[ORM\Column(name: 'description', type: 'text')]
    #[Assert\NotBlank]
    #[Assert\Length(
        min: 1,
        max: 1000,
        minMessage: 'La description doit avoir au minimum {{ limit }} caractères',
        maxMessage: 'La description ne doit pas dépasser {{ limit }} caractères'
    )]
    private ?string $description = null;

    /**
     * NOTE: This is not a mapped field of entity metadata, just a simple property.
     */
    #[Vich\UploadableField(mapping: 'event_img', fileNameProperty: 'img', size: 'imgSize')]
    private ?File $imgFile = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $img = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $imgSize = null;

    #[ORM\Column(name: 'date', type: 'datetime')]
    #[Assert\DateTime]
    #[Assert\NotNull]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(name: 'max_date_of_last_registration', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $max_date_of_last_registration = null;

    #[ORM\Column(name: 'need_proxy', type: 'boolean', options: ['default' => 0], nullable: true)]
    private ?bool $need_proxy = null;

    #[ORM\Column(name: 'anonymous_proxy', type: 'boolean', options: ['default' => 0], nullable: true)]
    private ?bool $anonymous_proxy = null;

    /**
     * @var Collection<int, Proxy>
     */
    #[ORM\OneToMany(targetEntity: Proxy::class, mappedBy: 'event', cascade: ['persist', 'remove'])]
    private Collection $proxies;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->proxies = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
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
     * @param File|null $image
     */
    public function setImgFile(?File $image = null): void
    {
        $this->imgFile = $image;

        if (null !== $image) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getImgFile(): ?File
    {
        return $this->imgFile;
    }

    /**
     * Set title
     *
     * @param string|null $title
     *
     * @return self
     */
    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title
     *
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Set date
     *
     * @param \DateTimeInterface|null $date
     *
     * @return self
     */
    public function setDate(?\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    /**
     * Get date
     *
     * @return \DateTimeInterface|null
     */
    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    /**
     * Get time
     *
     * @return \DateTimeInterface|null
     */
    public function getTime(): ?\DateTimeInterface
    {
        return $this->date;
    }

    /**
     * Add proxy
     *
     * @param Proxy $proxy
     *
     * @return self
     */
    public function addProxy(Proxy $proxy): self
    {
        if (!$this->proxies->contains($proxy)) {
            $this->proxies[] = $proxy;
            $proxy->setEvent($this);
        }

        return $this;
    }

    /**
     * Remove proxy
     *
     * @param Proxy $proxy
     *
     * @return self
     */
    public function removeProxy(Proxy $proxy): self
    {
        if ($this->proxies->removeElement($proxy)) {
            // set the owning side to null (unless already changed)
            if ($proxy->getEvent() === $this) {
                $proxy->setEvent(null);
            }
        }

        return $this;
    }

    /**
     * @param Beneficiary $beneficiary
     * @return Collection<int, Proxy>
     */
    public function getProxiesByOwner(Beneficiary $beneficiary): Collection
    {
        return $this->proxies->filter(function (Proxy $proxy) use ($beneficiary) {
            return ($proxy->getOwner() === $beneficiary);
        });
    }

    /**
     * @param Beneficiary $beneficiary
     * @return Collection<int, Proxy>
     */
    public function getProxiesByOwnerMembershipMainBeneficiary(Beneficiary $beneficiary): Collection
    {
        return $this->proxies->filter(function (Proxy $proxy) use ($beneficiary) {
            return ($proxy->getOwner()?->getMembership()?->getMainBeneficiary() === $beneficiary);
        });
    }

    /**
     * @param Membership $membership
     * @return Collection<int, Proxy>
     */
    public function getProxiesByGiver(Membership $membership): Collection
    {
        return $this->proxies->filter(function (Proxy $proxy) use ($membership) {
            return ($proxy->getGiver() === $membership);
        });
    }

    /**
     * Get proxies
     *
     * @return Collection<int, Proxy>
     */
    public function getProxies(): Collection
    {
        return $this->proxies;
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
     * Set needProxy
     *
     * @param bool|null $needProxy
     *
     * @return self
     */
    public function setNeedProxy(?bool $needProxy): self
    {
        $this->need_proxy = $needProxy;

        return $this;
    }

    /**
     * Get needProxy
     *
     * @return bool|null
     */
    public function getNeedProxy(): ?bool
    {
        return $this->need_proxy;
    }

    /**
     * Set anonymousProxy
     *
     * @param bool|null $anonymousProxy
     *
     * @return self
     */
    public function setAnonymousProxy(?bool $anonymousProxy): self
    {
        $this->anonymous_proxy = $anonymousProxy;

        return $this;
    }

    /**
     * Get anonymousProxy
     *
     * @return bool|null
     */
    public function getAnonymousProxy(): ?bool
    {
        return $this->anonymous_proxy;
    }

    /**
     * Set maxDateOfLastRegistration
     *
     * @param \DateTimeInterface|null $maxDateOfLastRegistration
     *
     * @return self
     */
    public function setMaxDateOfLastRegistration(?\DateTimeInterface $maxDateOfLastRegistration): self
    {
        $this->max_date_of_last_registration = $maxDateOfLastRegistration;

        return $this;
    }

    /**
     * Get maxDateOfLastRegistration
     *
     * @return \DateTimeInterface|null
     */
    public function getMaxDateOfLastRegistration(): ?\DateTimeInterface
    {
        if (is_null($this->max_date_of_last_registration)) {
            return $this->date;
        }
        return $this->max_date_of_last_registration;
    }

    /**
     * Set img
     *
     * @param string|null $img
     *
     * @return self
     */
    public function setImg(?string $img = null): self
    {
        $this->img = $img;

        return $this;
    }

    /**
     * Get img
     *
     * @return string|null
     */
    public function getImg(): ?string
    {
        return $this->img;
    }

    /**
     * Set imgSize
     *
     * @param int|null $imgSize
     *
     * @return self
     */
    public function setImgSize(?int $imgSize = null): self
    {
        $this->imgSize = $imgSize;

        return $this;
    }

    /**
     * Get imgSize
     *
     * @return int|null
     */
    public function getImgSize(): ?int
    {
        return $this->imgSize;
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
     * Get updatedAt
     *
     * @return \DateTimeInterface|null
     */
    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }
}

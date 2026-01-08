<?php

namespace App\Entity;

use App\Repository\ServiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Table(name: 'service')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: ServiceRepository::class)]
#[Vich\Uploadable]
class Service
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    private ?string $name = null;

    #[Assert\NotBlank]
    #[ORM\Column(name: 'description', type: Types::STRING, length: 255)]
    private ?string $description = null;

    #[Assert\NotBlank]
    #[ORM\Column(name: 'icon', type: Types::STRING, length: 255)]
    private ?string $icon = null;

    #[Assert\NotBlank]
    #[ORM\Column(name: 'slug', type: Types::STRING, length: 255)]
    private ?string $slug = null;

    #[ORM\Column(name: 'public', type: Types::BOOLEAN, unique: false, options: ['default' => 0], nullable: true)]
    private ?bool $public = null;

    #[ORM\Column(name: 'url', type: Types::STRING, length: 255, nullable: true)]
    private ?string $url = null;

    /**
     * NOTE: This is not a mapped field of entity metadata, just a simple property.
     */
    #[Vich\UploadableField(mapping: 'service_logo', fileNameProperty: 'logo', size: 'logoSize')]
    private ?File $logoFile = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $logo = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $logoSize = null;

    #[ORM\OneToMany(targetEntity: Client::class, mappedBy: 'service', cascade: ['persist', 'remove'])]
    private Collection $clients;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->clients = new ArrayCollection();
    }

    #[ORM\PrePersist]
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
     * If manually uploading a file (i.e. not using Symfony Form) ensure an instance
     * of 'UploadedFile' is injected into this setter to trigger the  update. If this
     * bundle's configuration parameter 'inject_on_load' is set to 'true' this setter
     * must be able to accept an instance of 'File' as the bundle will inject one here
     * during Doctrine hydration.
     *
     * @param File|null $image
     */
    public function setLogoFile(?File $image = null): void
    {
        $this->logoFile = $image;

        if (null !== $image) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    /**
     * @return File|null
     */
    public function getLogoFile(): ?File
    {
        return $this->logoFile;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return Service
     */
    public function setName(string $name): self
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
     * Set description
     *
     * @param string $description
     *
     * @return Service
     */
    public function setDescription(string $description): self
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
     * Set url
     *
     * @param string|null $url
     *
     * @return Service
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
     * Add client
     *
     * @param Client $client
     *
     * @return Service
     */
    public function addClient(Client $client): self
    {
        $this->clients[] = $client;

        return $this;
    }

    /**
     * Remove client
     *
     * @param Client $client
     * @return self
     */
    public function removeClient(Client $client): self
    {
        $this->clients->removeElement($client);
        return $this;
    }

    /**
     * Get clients
     *
     * @return Collection
     */
    public function getClients(): Collection
    {
        return $this->clients;
    }

    /**
     * Set logo
     *
     * @param string|null $logo
     *
     * @return Service
     */
    public function setLogo(?string $logo): self
    {
        $this->logo = $logo;

        return $this;
    }

    /**
     * Get logo
     *
     * @return string|null
     */
    public function getLogo(): ?string
    {
        return $this->logo;
    }

    /**
     * Set logoSize
     *
     * @param int|null $logoSize
     *
     * @return Service
     */
    public function setLogoSize(?int $logoSize): self
    {
        $this->logoSize = $logoSize;

        return $this;
    }

    /**
     * Get logoSize
     *
     * @return int|null
     */
    public function getLogoSize(): ?int
    {
        return $this->logoSize;
    }

    /**
     * Set updatedAt
     *
     * @param \DateTimeInterface $updatedAt
     *
     * @return Service
     */
    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
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

    /**
     * Set public.
     *
     * @param bool|null $public
     *
     * @return Service
     */
    public function setPublic(?bool $public = null): self
    {
        $this->public = $public;

        return $this;
    }

    /**
     * Get public.
     *
     * @return bool|null
     */
    public function getPublic(): ?bool
    {
        return $this->public;
    }

    /**
     * Set icon.
     *
     * @param string $icon
     *
     * @return Service
     */
    public function setIcon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Get icon.
     *
     * @return string|null
     */
    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * Set slug.
     *
     * @param string $slug
     *
     * @return Service
     */
    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * Get slug.
     *
     * @return string|null
     */
    public function getSlug(): ?string
    {
        return $this->slug;
    }
}
